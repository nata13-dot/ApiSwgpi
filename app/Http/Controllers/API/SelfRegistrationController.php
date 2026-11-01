<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\SelfRegistrationCodeMail;
use App\Models\Career;
use App\Models\CareerSetting;
use App\Models\SubjectGroup;
use App\Models\User;
use App\Models\UserCareer;
use App\Services\AcademicWorkflowService;
use App\Support\CareerContext;
use App\Support\SelfRegistrationRule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Autoregistro de alumnos. La jefatura (o la administración general) lo abre por carrera hasta una fecha;
 * el alumno captura sus datos, recibe un código en su correo institucional y la cuenta se crea al
 * capturarlo, ya inscrita en el grupo que eligió del periodo abierto.
 */
class SelfRegistrationController extends Controller
{
    private const CODE_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    /** Carreras con autoregistro abierto; el login muestra "Crear cuenta" sólo si hay alguna. */
    public function index()
    {
        $careers = Career::where('activa', true)->orderBy('nombre')->get()
            ->map(fn (Career $career) => $this->openCareerPayload($career))
            ->filter()->values();

        return response()->json(['open' => $careers->isNotEmpty(), 'careers' => $careers]);
    }

    public function request(Request $request)
    {
        $validated = $request->validate([
            'carrera_id' => 'required|integer|exists:carreras,id',
            'id' => 'required|string|max:20',
            'email' => 'required|email|max:255',
            'nombres' => 'required|string|max:150',
            'apa' => 'required|string|max:100',
            'ama' => 'nullable|string|max:100',
            'grupo_id' => 'required|integer',
            'password' => 'required|string|min:8|max:72|confirmed',
        ], [], [
            'id' => 'número de control', 'email' => 'correo institucional', 'apa' => 'apellido paterno',
            'ama' => 'apellido materno', 'grupo_id' => 'grupo', 'password' => 'contraseña',
        ]);

        $career = Career::findOrFail($validated['carrera_id']);
        $open = $this->openCareerPayload($career);
        if (!$open) {
            throw ValidationException::withMessages(['carrera_id' => ['El autoregistro de esta carrera está cerrado.']]);
        }

        $id = SelfRegistrationRule::normalizeId($validated['id']);
        $email = strtolower(trim($validated['email']));
        if ($violation = SelfRegistrationRule::violation($open['rule'], $id, $email)) {
            throw ValidationException::withMessages(['email' => [$violation]]);
        }
        if (!collect($open['groups'])->contains('id', (int) $validated['grupo_id'])) {
            throw ValidationException::withMessages(['grupo_id' => ['Elige un grupo del periodo abierto de tu carrera.']]);
        }
        $this->ensureAvailable($id, $email);

        $code = (string) random_int(100000, 999999);
        $name = trim($validated['nombres']);
        $data = [
            'nombres' => $name,
            'apa' => trim($validated['apa']),
            'ama' => trim((string) ($validated['ama'] ?? '')) ?: null,
            'grupo_id' => (int) $validated['grupo_id'],
            'password' => Hash::make($validated['password']),
        ];

        DB::transaction(function () use ($career, $id, $email, $data, $code) {
            // Un reintento reemplaza la solicitud anterior del mismo número de control o correo.
            DB::table('autoregistros_pendientes')->where('matricula', $id)->orWhere('correo', $email)->delete();
            DB::table('autoregistros_pendientes')->insert([
                'carrera_id' => $career->id,
                'matricula' => $id,
                'correo' => $email,
                'datos' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'codigo' => Hash::make($code),
                'expira_en' => now()->addMinutes(self::CODE_MINUTES),
                'creado_en' => now(),
            ]);
        });

        try {
            Mail::to($email)->send(new SelfRegistrationCodeMail($name, $career->nombre, $code));
        } catch (\Throwable $e) {
            logger()->error('No se pudo enviar el código de autoregistro.', ['mail_mailer' => config('mail.default'), 'error' => $e->getMessage()]);
            DB::table('autoregistros_pendientes')->where('correo', $email)->delete();

            return response()->json(['error' => 'No se pudo enviar el código a tu correo. Intenta de nuevo en unos minutos.'], 500);
        }

        return response()->json(['message' => 'Enviamos un código de seis dígitos a tu correo institucional.', 'email' => $email, 'expires_in_minutes' => self::CODE_MINUTES]);
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ], [], ['email' => 'correo institucional', 'code' => 'código']);

        $email = strtolower(trim($validated['email']));
        $pending = DB::table('autoregistros_pendientes')->where('correo', $email)->first();
        if (!$pending || Carbon::parse($pending->expira_en)->isPast()) {
            throw ValidationException::withMessages(['code' => ['El código venció o no existe. Vuelve a enviar tus datos para recibir uno nuevo.']]);
        }
        if ((int) $pending->intentos >= self::MAX_ATTEMPTS) {
            DB::table('autoregistros_pendientes')->where('id', $pending->id)->delete();
            throw ValidationException::withMessages(['code' => ['Superaste los intentos permitidos. Vuelve a enviar tus datos.']]);
        }
        if (!Hash::check($validated['code'], $pending->codigo)) {
            DB::table('autoregistros_pendientes')->where('id', $pending->id)->increment('intentos');
            throw ValidationException::withMessages(['code' => ['El código no es correcto.']]);
        }

        $this->ensureAvailable($pending->matricula, $email);
        $career = Career::findOrFail($pending->carrera_id);
        $data = json_decode($pending->datos, true);

        DB::transaction(function () use ($pending, $career, $data, $email) {
            $user = new User();
            $user->forceFill([
                'id' => $pending->matricula,
                'nombres' => $data['nombres'],
                'apellido_paterno' => $data['apa'],
                'apellido_materno' => $data['ama'],
                'correo' => $email,
                'contrasena' => $data['password'],
                'debe_cambiar_contrasena' => false,
                'activo' => true,
                'perfil_completado_en' => now(),
            ])->save();
            UserCareer::create([
                'usuario_id' => $user->id, 'carrera_id' => $career->id, 'perfil_id' => \App\Support\Profiles::STUDENT,
                'es_principal' => true, 'activo' => true,
            ]);
            User::forgetPrincipalProfiles();

            // El grupo se asigna con las mismas validaciones que usa la jefatura (periodo abierto, grupo activo).
            app(CareerContext::class)->set($user, $career, \App\Support\Profiles::STUDENT);
            app(AcademicWorkflowService::class)->assign($user->id, SubjectGroup::withoutGlobalScopes()->findOrFail($data['grupo_id']));
            DB::table('autoregistros_pendientes')->where('id', $pending->id)->delete();
        });

        return response()->json(['message' => 'Tu cuenta quedó activa. Ya puedes iniciar sesión con tu número de control.', 'id' => $pending->matricula], 201);
    }

    /** Reglas de correo por carrera (sólo administración general). */
    public function rules()
    {
        return response()->json(Career::orderBy('nombre')->get(['id', 'clave', 'nombre', 'nombre_corto', 'activa'])->map(fn (Career $career) => [
            'career' => $career,
            'rule' => SelfRegistrationRule::publicPayload(SelfRegistrationRule::forCareer($career->id)),
            'open_until' => $this->openUntil($career->id)?->toIso8601String(),
        ]));
    }

    public function updateRule(Request $request, Career $career)
    {
        $validated = $request->validate([
            'number_prefix' => 'nullable|string|max:8|regex:/^\d*$/',
            'digits' => 'required|integer|min:4|max:12',
            'domain' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ], [], ['number_prefix' => 'inicio del número de control', 'digits' => 'dígitos', 'domain' => 'dominio']);
        if (strlen((string) ($validated['number_prefix'] ?? '')) > (int) $validated['digits']) {
            throw ValidationException::withMessages(['number_prefix' => ['El inicio no puede ser más largo que el número de control.']]);
        }

        $rule = SelfRegistrationRule::normalize($validated);
        CareerSetting::setValueForCareer($career->id, 'self_registration_rule', $rule, 'array', 'Formato del número de control y del correo institucional para el autoregistro');

        return response()->json(['message' => 'Regla guardada', 'rule' => SelfRegistrationRule::publicPayload($rule)]);
    }

    public static function openUntilFor(int $careerId): ?Carbon
    {
        $until = CareerSetting::valueForCareer($careerId, 'self_registration_until');

        return $until ? Carbon::parse($until) : null;
    }

    private function openUntil(int $careerId): ?Carbon
    {
        return self::openUntilFor($careerId);
    }

    /** Datos públicos de la carrera si su autoregistro está abierto y tiene periodo abierto con grupos. */
    private function openCareerPayload(Career $career): ?array
    {
        $until = $this->openUntil($career->id);
        if (!$career->activa || !$until || $until->isPast()) {
            return null;
        }
        $period = app(AcademicWorkflowService::class)->period($career->id);
        if (!$period) {
            return null;
        }

        $groups = SubjectGroup::withoutGlobalScopes()->where('carrera_id', $career->id)->where('periodo_id', $period->id)
            ->where('activo', true)->whereNotNull('semestre')->where('clave_grupo', 'not like', 'SIN\_%')
            ->orderBy('semestre')->orderBy('clave_grupo')->get(['id', 'nombre', 'semestre', 'clave_grupo'])
            ->map(fn ($group) => ['id' => $group->id, 'nombre' => $group->nombre, 'semestre' => (int) $group->semestre, 'grupo' => $group->clave_grupo]);
        if ($groups->isEmpty()) {
            return null;
        }

        return [
            'id' => $career->id,
            'nombre' => $career->nombre,
            'nombre_corto' => $career->nombre_corto,
            'closes_at' => $until->toIso8601String(),
            'period' => $period->nombre,
            'rule' => SelfRegistrationRule::publicPayload(SelfRegistrationRule::forCareer($career->id)),
            'groups' => $groups->all(),
        ];
    }

    private function ensureAvailable(string $id, string $email): void
    {
        if (User::withoutGlobalScopes()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['id' => ['Ese número de control ya tiene cuenta. Inicia sesión o recupera tu contraseña.']]);
        }
        if (User::withoutGlobalScopes()->whereRaw('LOWER(correo) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => ['Ese correo ya está registrado en otra cuenta.']]);
        }
    }
}
