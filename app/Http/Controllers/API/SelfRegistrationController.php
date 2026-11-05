<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\SelfRegistrationCodeMail;
use App\Mail\SelfRegistrationResultMail;
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
 * el alumno captura sus datos y confirma su correo institucional con un código. La solicitud queda en
 * revisión y la cuenta se crea sólo cuando la aprueba la jefatura, su asistencia o la administración
 * general, ya inscrita en el grupo que eligió del periodo abierto.
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
        if (DB::table('autoregistros_pendientes')->where('estado', 'por_aprobar')->where(fn ($q) => $q->where('matricula', $id)->orWhere('correo', $email))->exists()) {
            throw ValidationException::withMessages(['id' => ['Ya hay una solicitud en revisión con ese número de control o correo.']]);
        }

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
        if ($pending?->estado === 'por_aprobar') {
            return response()->json(['message' => 'Tu solicitud ya está en revisión.', 'id' => $pending->matricula, 'status' => 'por_aprobar']);
        }
        if (!$pending || $pending->estado !== 'codigo' || Carbon::parse($pending->expira_en)->isPast()) {
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
        DB::table('autoregistros_pendientes')->where('id', $pending->id)->update(['estado' => 'por_aprobar', 'codigo' => '', 'verificado_en' => now()]);

        // Es una petición pública: el aviso a quienes revisan no debe impedir responderle al alumno.
        \App\Services\ActivityNotificationService::send(
            DB::table('usuario_carrera')->where('carrera_id', $pending->carrera_id)->where('activo', true)->whereIn('perfil_id', [1, 5, 6])->pluck('usuario_id'),
            null, 'solicitud_cuenta', 'Solicitud de cuenta', 'Un alumno nuevo espera aprobación.', '/registration-requests', (int) $pending->carrera_id
        );

        return response()->json(['message' => 'Confirmamos tu correo. Tu solicitud quedó en revisión; te avisaremos por correo cuando la jefatura de tu carrera la responda.', 'id' => $pending->matricula, 'status' => 'por_aprobar']);
    }

    /** Solicitudes con el correo ya confirmado. La jefatura y su asistencia sólo ven las de su carrera. */
    public function requests(Request $request)
    {
        $validated = $request->validate(['status' => 'nullable|in:por_aprobar,rechazada']);
        $user = auth('api')->user();
        // Las rechazadas no se conservan más de 90 días, ni los códigos que nunca se confirmaron.
        DB::table('autoregistros_pendientes')->where('estado', 'rechazada')->where('revisado_en', '<', now()->subDays(90))->delete();
        DB::table('autoregistros_pendientes')->where('estado', 'codigo')->where('expira_en', '<', now()->subDay())->delete();

        $rows = DB::table('autoregistros_pendientes')
            ->where('estado', $validated['status'] ?? 'por_aprobar')
            ->when(!$user->isGeneralAdmin(), fn ($q) => $q->where('carrera_id', app(CareerContext::class)->careerId()))
            ->orderBy('verificado_en')
            ->get();
        $careers = Career::whereIn('id', $rows->pluck('carrera_id')->unique())->get(['id', 'nombre', 'nombre_corto'])->keyBy('id');
        $groups = SubjectGroup::withoutGlobalScopes()
            ->whereIn('id', $rows->map(fn ($row) => (int) (json_decode($row->datos, true)['grupo_id'] ?? 0))->unique())
            ->get(['id', 'nombre', 'semestre', 'clave_grupo'])->keyBy('id');

        return response()->json($rows->map(function ($row) use ($careers, $groups) {
            $data = json_decode($row->datos, true);
            $group = $groups[(int) ($data['grupo_id'] ?? 0)] ?? null;

            // Los datos guardados incluyen el hash de la contraseña: sólo se devuelve lo necesario para revisar.
            return [
                'id' => $row->id, 'matricula' => $row->matricula, 'correo' => $row->correo,
                'nombres' => $data['nombres'] ?? '', 'apa' => $data['apa'] ?? '', 'ama' => $data['ama'] ?? null,
                'carrera' => $careers[$row->carrera_id]->nombre ?? null,
                'grupo' => $group ? ['nombre' => $group->nombre, 'semestre' => $group->semestre, 'clave' => $group->clave_grupo] : null,
                'estado' => $row->estado, 'verificado_en' => $row->verificado_en, 'revisado_en' => $row->revisado_en, 'motivo' => $row->motivo_rechazo,
            ];
        })->values());
    }

    public function approve(int $id)
    {
        $pending = $this->reviewable($id);
        $this->ensureAvailable($pending->matricula, $pending->correo);
        $career = Career::findOrFail($pending->carrera_id);
        $data = json_decode($pending->datos, true);

        DB::transaction(function () use ($pending, $career, $data) {
            $user = new User();
            $user->forceFill([
                'id' => $pending->matricula,
                'nombres' => $data['nombres'],
                'apellido_paterno' => $data['apa'],
                'apellido_materno' => $data['ama'],
                'correo' => $pending->correo,
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

        return response()->json([
            'message' => "La cuenta de {$pending->matricula} quedó activa.",
            'email_sent' => $this->notifyResult($pending, $data, $career, true),
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate(['reason' => 'required|string|min:5|max:500'], [], ['reason' => 'motivo']);
        $pending = $this->reviewable($id);
        $data = json_decode($pending->datos, true);
        DB::table('autoregistros_pendientes')->where('id', $pending->id)->update([
            'estado' => 'rechazada', 'motivo_rechazo' => trim($validated['reason']),
            'revisado_por' => auth('api')->id(), 'revisado_en' => now(),
        ]);

        return response()->json([
            'message' => 'Solicitud rechazada.',
            'email_sent' => $this->notifyResult($pending, $data, Career::findOrFail($pending->carrera_id), false, trim($validated['reason'])),
        ]);
    }

    /** Aviso para quien intenta entrar con una solicitud que aún no es cuenta. Exige la contraseña que eligió. */
    public static function loginNotice(string $id, string $password): ?string
    {
        // Durante el despliegue el código puede llegar antes que la migración: el inicio de sesión no debe fallar por eso.
        if (!\App\Support\SchemaCache::hasColumn('autoregistros_pendientes', 'estado')) {
            return null;
        }

        $pending = DB::table('autoregistros_pendientes')
            ->where('matricula', SelfRegistrationRule::normalizeId($id))
            ->whereIn('estado', ['por_aprobar', 'rechazada'])
            ->first();
        if (!$pending || !Hash::check($password, (string) (json_decode($pending->datos, true)['password'] ?? ''))) {
            return null;
        }

        return $pending->estado === 'por_aprobar'
            ? 'Tu solicitud de cuenta sigue en revisión. Te avisaremos por correo cuando la jefatura de tu carrera la responda.'
            : "Tu solicitud de cuenta fue rechazada: {$pending->motivo_rechazo}";
    }

    private function reviewable(int $id): object
    {
        $pending = DB::table('autoregistros_pendientes')->where('id', $id)->where('estado', 'por_aprobar')->first();
        abort_unless($pending, 404, 'La solicitud no existe o ya fue revisada.');
        $user = auth('api')->user();
        abort_unless($user->isGeneralAdmin() || (int) $pending->carrera_id === (int) app(CareerContext::class)->careerId(), 403, 'La solicitud pertenece a otra carrera.');

        return $pending;
    }

    /** Un fallo del correo no deshace la revisión: la pantalla avisa para contactar al alumno por otra vía. */
    private function notifyResult(object $pending, array $data, Career $career, bool $approved, ?string $reason = null): bool
    {
        try {
            Mail::to($pending->correo)->send(new SelfRegistrationResultMail($data['nombres'] ?? '', $career->nombre, $pending->matricula, $approved, $reason));

            return true;
        } catch (\Throwable $e) {
            logger()->error('No se pudo avisar el resultado del autoregistro.', ['mail_mailer' => config('mail.default'), 'error' => $e->getMessage()]);

            return false;
        }
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
            'career_code' => ['nullable', 'string', 'regex:/^\d{2}$/'],
            'domain' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ], [], ['career_code' => 'clave de la carrera', 'domain' => 'dominio']);

        // Las apps anteriores no envían la clave: guardar desde ellas no debe borrarla.
        $rule = SelfRegistrationRule::normalize($validated + SelfRegistrationRule::forCareer($career->id));
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

    /** Por qué el inicio de sesión no muestra «Crear cuenta» para esta carrera; null si lo muestra. */
    public static function blockerFor(Career $career): ?string
    {
        $until = self::openUntilFor($career->id);
        if (!$until || $until->isPast()) {
            return 'La ventana de autoregistro está cerrada.';
        }
        if (!$career->activa) {
            return 'La carrera está inactiva.';
        }
        $period = app(AcademicWorkflowService::class)->period($career->id);
        if (!$period) {
            return 'La carrera no tiene un periodo abierto.';
        }
        // Los alumnos nuevos entran a 5°, sólo en los grupos que la carrera definió.
        if (!\App\Support\NewStudentGroups::allowedIn($period)) {
            return "El periodo abierto ({$period->nombre}) no tiene 5° semestre: los alumnos nuevos sólo se registran de julio a diciembre.";
        }
        if (self::newStudentGroups($career, $period)->isEmpty()) {
            return "No hay grupos de 5° activos en el periodo {$period->nombre}. Guarda los grupos de alumnos nuevos o activa los que estén inactivos.";
        }

        return null;
    }

    private static function newStudentGroups(Career $career, object $period)
    {
        return SubjectGroup::withoutGlobalScopes()->where('carrera_id', $career->id)->where('periodo_id', $period->id)
            ->where('activo', true)->where('semestre', \App\Support\NewStudentGroups::SEMESTER)->whereIn('clave_grupo', \App\Support\NewStudentGroups::letters($career->id))
            ->orderBy('clave_grupo')->get(['id', 'nombre', 'semestre', 'clave_grupo']);
    }

    /** Datos públicos de la carrera si su autoregistro está abierto y tiene periodo abierto con grupos. */
    private function openCareerPayload(Career $career): ?array
    {
        if (self::blockerFor($career)) {
            return null;
        }
        $until = $this->openUntil($career->id);
        $period = app(AcademicWorkflowService::class)->period($career->id);
        $groups = self::newStudentGroups($career, $period)
            ->map(fn ($group) => ['id' => $group->id, 'nombre' => $group->nombre, 'semestre' => (int) $group->semestre, 'grupo' => $group->clave_grupo]);

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
