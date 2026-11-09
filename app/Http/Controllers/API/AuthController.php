<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetTokenMail;
use App\Models\Career;
use App\Models\SystemSetting;
use App\Models\CareerSetting;
use App\Models\User;
use App\Support\CareerContext;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const MAX_RESET_ATTEMPTS = 5;

    public function __construct(private readonly CareerContext $careerContext)
    {
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'id' => 'required|string',
                'password' => 'required|string',
                'remember' => 'nullable|boolean',
                'carrera_id' => 'nullable|integer|exists:carreras,id',
                'device' => 'nullable|in:app',
            ]);

            // Los números de control con b se guardan en minúsculas; se ignoran espacios accidentales.
            $user = User::find(strtolower(trim($credentials['id'])));

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                // Con la contraseña correcta se explica el estado de la solicitud; así no se revela a quien no la conoce.
                if (!$user && ($notice = \App\Http\Controllers\API\SelfRegistrationController::loginNotice($credentials['id'], $credentials['password']))) {
                    return response()->json(['error' => $notice, 'code' => 'SELF_REGISTRATION_PENDING'], 403);
                }
                if (!$user && ($archived = User::archived()->find(strtolower(trim($credentials['id']))))
                    && Hash::check($credentials['password'], $archived->password)) {
                    return response()->json(['error' => 'Tu cuenta está en el archivo histórico. Pide a la administración de tu carrera que la restaure.', 'code' => 'ACCOUNT_ARCHIVED'], 403);
                }

                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            if (!$user->activo) {
                return response()->json(['error' => 'Cuenta desactivada'], 403);
            }

            // Solo la app móvil conserva la sesión; la protege el bloqueo con huella del teléfono. En un navegador
            // se piden credenciales al expirar o por inactividad, aunque el cliente pida recordarla.
            $isApp = ($credentials['device'] ?? null) === 'app';
            $remember = $isApp;
            $access = $this->resolveCareerAccess($user, $request->integer('carrera_id') ?: null);
            if (!$access) {
                return response()->json(['error' => 'La cuenta no tiene una carrera activa asignada.'], 403);
            }
            $appSession = $isApp ? app(\App\Services\AppSessionService::class)->create($user->id) : null;
            $token = $this->issueToken($user, $remember, $access['career']->id, $access['profile_id'], $appSession);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user, $access['career'], $access['profile_id']),
                'remember' => $remember,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function me(Request $request)
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        if (!$user->activo) {
            return response()->json(['error' => 'Cuenta desactivada'], 403);
        }
        return response()->json([
            'user' => $this->userPayload(
                $user,
                $this->careerContext->career(),
                $this->careerContext->profileId()
            ),
        ]);
    }

    public function careers()
    {
        $user = auth('api')->user();

        return response()->json([
            'careers' => $this->availableCareers($user),
            'active_career_id' => $this->careerContext->careerId() ?? $this->careerIdFromToken(),
        ]);
    }

    public function switchCareer(Request $request)
    {
        $validated = $request->validate([
            'carrera_id' => 'required|integer|exists:carreras,id',
        ]);

        $user = auth('api')->user();
        $access = $this->resolveCareerAccess($user, (int) $validated['carrera_id']);
        if (!$access) {
            return response()->json([
                'error' => 'No tienes acceso a la carrera seleccionada.',
                'code' => 'CAREER_ACCESS_DENIED',
            ], 403);
        }

        $remember = $this->rememberedToken();
        $newToken = $this->issueToken($user, $remember, $access['career']->id, $access['profile_id'], \App\Services\AppSessionService::current());
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (\Throwable) {
            // El token nuevo sigue siendo válido aunque el anterior ya haya expirado.
        }

        return response()->json([
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'remember' => $remember,
            'user' => $this->userPayload($user, $access['career'], $access['profile_id']),
        ]);
    }

    public function logout(Request $request)
    {
        try {
            if ($appSession = \App\Services\AppSessionService::current()) {
                app(\App\Services\AppSessionService::class)->revoke($appSession);
            }
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesión'], 500);
        }
    }

    public function refresh()
    {
        try {
            // getPayload() rechaza los tokens vencidos, justo los que hay que renovar; se decodifica en modo renovación.
            $oldToken = JWTAuth::parseToken()->getToken();
            $oldPayload = JWTAuth::manager()->setRefreshFlow()->decode($oldToken);
            JWTAuth::manager()->setRefreshFlow(false);
            $careerId = $oldPayload->get('career_id');
            $appSession = $oldPayload->get('app_session');
            // Fuera de la app, un token vencido o inactivo termina la sesión, aunque se haya emitido como recordada.
            $remember = (bool) $appSession;
            if (!$remember && ((int) $oldPayload->get('exp') <= now()->timestamp || \App\Http\Middleware\EnsureUserIsActive::tokenIsIdle((string) $oldToken))) {
                return response()->json(['error' => 'La sesion ya no puede renovarse'], 401);
            }
            if ($appSession) {
                $sessionUser = User::find($oldPayload->get('sub'));
                if (!$sessionUser || !app(\App\Services\AppSessionService::class)->isActive($appSession, $sessionUser->id)) {
                    return response()->json(['error' => 'La sesion ya no puede renovarse'], 401);
                }
            }
            // El token renovado de la app conserva su duración; si no, caería al vencimiento de un token web.
            $factory = JWTAuth::factory();
            $ttl = $factory->getTTL();
            if ($appSession) {
                $factory->setTTL(\App\Services\AppSessionService::TOKEN_TTL_MINUTES);
            }
            try {
                $token = JWTAuth::setToken($oldToken)->refresh();
            } finally {
                $factory->setTTL($ttl);
            }
            $user = JWTAuth::setToken($token)->authenticate();
            if (!$user || !$user->activo) {
                return response()->json(['error' => 'Cuenta no disponible'], 403);
            }
            $access = $this->resolveCareerAccess($user, $careerId === null ? null : (int) $careerId);
            if (!$access) {
                return response()->json(['error' => 'La carrera de la sesión ya no está disponible.'], 403);
            }
            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user, $access['career'], $access['profile_id']),
                'remember' => $remember,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'La sesion ya no puede renovarse'], 401);
        }
    }

    public function heartbeat()
    {
        return response()->json([
            'message' => 'Sesion activa',
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function requestPasswordReset(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:10',
                'email' => 'required|email',
            ], [], [
                'id' => 'No. de Control, No. de empleado',
                'email' => 'correo',
            ]);

            $user = User::where('id', $validated['id'])
                ->whereRaw('LOWER(correo) = ?', [strtolower($validated['email'])])
                ->where('activo', true)
                ->first();

            if (!$user) {
                return response()->json(['error' => 'El número de control o empleado y el correo no coinciden con una cuenta activa.'], 422);
            }

            $token = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            Cache::forget($this->resetAttemptsKey($user->email));

            Mail::to($user->email)->send(new PasswordResetTokenMail($user, $token));

            return response()->json(['message' => 'Código enviado al correo registrado.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            logger()->error('Error inesperado solicitando token de recuperacion.', [
                'mail_mailer' => config('mail.default'),
                'mail_from' => config('mail.from.address'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'No pudimos enviar el código. Intenta de nuevo en unos minutos.',
            ], 500);
        }
    }

    public function verifyPasswordResetToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:10',
                'email' => 'required|email',
                'token' => 'required|string|size:6',
            ], [], [
                'id' => 'número de control o empleado',
                'email' => 'correo',
                'token' => 'código',
            ]);

            $user = $this->validResetUser($validated['id'], $validated['email']);
            if (!$user || !$this->validResetToken($user->email, $validated['token'])) {
                return response()->json(['error' => 'El código no es válido o ya venció.'], 422);
            }

            return response()->json(['message' => 'Código validado.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    public function resetPasswordWithToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:10',
                'email' => 'required|email',
                'token' => 'required|string|size:6',
                'password' => 'required|string|min:8|max:72|confirmed',
            ], [], [
                'id' => 'número de control o empleado',
                'email' => 'correo',
                'token' => 'código',
                'password' => 'contraseña',
            ]);

            $user = $this->validResetUser($validated['id'], $validated['email']);
            if (!$user || !$this->validResetToken($user->email, $validated['token'])) {
                return response()->json(['error' => 'El código no es válido o ya venció.'], 422);
            }

            $user->update(['contrasena' => Hash::make($validated['password'])]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $access = $this->resolveCareerAccess($user);
            if (!$access) {
                return response()->json(['error' => 'La cuenta no tiene una carrera activa asignada.'], 403);
            }
            $jwt = $this->issueToken($user, false, $access['career']->id, $access['profile_id']);

            return response()->json([
                'message' => 'Contraseña actualizada correctamente.',
                'access_token' => $jwt,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user, $access['career'], $access['profile_id']),
                'remember' => false,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    private function validResetUser(string $id, string $email): ?User
    {
        return User::where('id', $id)
            ->whereRaw('LOWER(correo) = ?', [strtolower($email)])
            ->where('activo', true)
            ->first();
    }

    private function validResetToken(string $email, string $token): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$record || !$record->created_at || Carbon::parse($record->created_at)->lt(now()->subMinutes(15))) {
            return false;
        }

        if (Hash::check($token, $record->token)) {
            return true;
        }

        // Tras varios intentos fallidos el código deja de servir, aunque cambie la dirección IP.
        $attempts = Cache::add($this->resetAttemptsKey($email), 0, now()->addMinutes(15)) ? 0 : (int) Cache::get($this->resetAttemptsKey($email), 0);
        Cache::put($this->resetAttemptsKey($email), $attempts + 1, now()->addMinutes(15));
        if ($attempts + 1 >= self::MAX_RESET_ATTEMPTS) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        return false;
    }

    private function resetAttemptsKey(string $email): string
    {
        return 'auth:reset_attempts:'.sha1(strtolower($email));
    }

    private function userPayload(User $user, ?Career $career = null, ?int $activeProfileId = null): array
    {
        $activeProfileId ??= (int) $user->perfil_id;
        $payload = [
            'id' => $user->id,
            'nombres' => $user->nombres,
            'apa' => $user->apa,
            'ama' => $user->ama,
            'email' => $user->email,
            'perfil_id' => $user->globalProfileId(),
            'active_profile_id' => $activeProfileId,
            'capabilities' => \App\Http\Middleware\CheckRole::grantsForProfile($activeProfileId),
            'is_general_admin' => $user->globalProfileId() === 4,
            'active_career' => $career ? $this->careerPayload($career, $activeProfileId) : null,
            'careers' => $this->availableCareers($user),
            'semestre' => $user->semestre ?? null,
            'grupo' => $user->grupo ?? null,
            'photo_path' => $user->photo_path,
            'profile_completed_at' => $user->profile_completed_at,
            'must_change_password' => (bool) $user->debe_cambiar_contrasena,
            'view_as' => $this->viewAsSession(),
        ];
        // Gestionan salas y evaluaciones el administrador general, jefatura, asistencia y coordinación.
        $payload['is_evaluation_manager'] = in_array($activeProfileId, [1, 4, 5, 6, 7], true);

        return $payload;
    }

    /** Datos del administrador general cuando la sesión es un "ver como" de sólo lectura. */
    private function viewAsSession(): ?array
    {
        try {
            $adminId = JWTAuth::parseToken()->getPayload()->get('view_as_by');
        } catch (\Throwable) {
            return null;
        }
        if (!$adminId) return null;
        $admin = User::withoutGlobalScopes()->find($adminId);
        $simulated = app(\App\Support\CareerContext::class)->simulatedProfile();

        return ['by_id' => $adminId, 'by_name' => trim(($admin->nombres ?? '').' '.($admin->apellido_paterno ?? '')), 'read_only' => true,
            'simulated_profile' => $simulated, 'simulated_label' => $simulated ? \App\Support\Profiles::label($simulated) : null];
    }

    private function issueToken(User $user, bool $remember, int $careerId, int $profileId, ?string $appSession = null): string
    {
        $claims = ['remember' => $remember, 'career_id' => $careerId, 'career_role' => $profileId];
        if (!$appSession) {
            return JWTAuth::claims($claims)->fromUser($user);
        }

        $factory = JWTAuth::factory();
        $ttl = $factory->getTTL();
        $factory->setTTL(\App\Services\AppSessionService::TOKEN_TTL_MINUTES);
        try {
            return JWTAuth::claims($claims + ['app_session' => $appSession])->fromUser($user);
        } finally {
            $factory->setTTL($ttl);
        }
    }

    private function resolveCareerAccess(User $user, ?int $careerId = null): ?array
    {
        if ($user->globalProfileId() === 4) {
            $career = Career::query()
                ->where('activa', true)
                ->when($careerId, fn ($query) => $query->whereKey($careerId))
                ->orderBy('id')
                ->first();

            return $career ? ['career' => $career, 'profile_id' => 4] : null;
        }

        $membership = $user->careerMemberships()
            ->with('career')
            ->where('activo', true)
            ->whereHas('career', fn ($query) => $query->where('activa', true))
            ->when(
                $careerId,
                fn ($query) => $query->where('carrera_id', $careerId),
                fn ($query) => $query->orderByDesc('es_principal')->orderBy('id')
            )
            ->first();

        return $membership
            ? ['career' => $membership->career, 'profile_id' => (int) $membership->perfil_id]
            : null;
    }

    private function availableCareers(User $user): array
    {
        if ($user->globalProfileId() === 4) {
            return Career::query()
                ->where('activa', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (Career $career) => $this->careerPayload($career, 4))
                ->all();
        }

        return $user->careerMemberships()
            ->with('career')
            ->where('activo', true)
            ->whereHas('career', fn ($query) => $query->where('activa', true))
            ->orderByDesc('es_principal')
            ->get()
            ->map(fn ($membership) => $this->careerPayload(
                $membership->career,
                (int) $membership->perfil_id,
                (bool) $membership->es_principal
            ))
            ->all();
    }

    private function careerPayload(Career $career, int $profileId, bool $isPrimary = false): array
    {
        return [
            'id' => (int) $career->id,
            'clave' => $career->clave,
            'slug' => $career->slug,
            'nombre' => $career->nombre,
            'nombre_corto' => $career->nombre_corto,
            'color_primario' => $career->color_primario,
            'color_secundario' => $career->color_secundario,
            'color_acento' => $career->color_acento,
            'lema' => $career->lema,
            'logo_ruta' => $career->logo_ruta,
            'portada_ruta' => $career->portada_ruta,
            'perfil_id' => $profileId,
            'es_principal' => $isPrimary,
        ];
    }

    private function rememberedToken(): bool
    {
        try {
            return (bool) JWTAuth::parseToken()->getPayload()->get('remember');
        } catch (\Throwable) {
            return false;
        }
    }

    private function careerIdFromToken(): ?int
    {
        try {
            $value = JWTAuth::parseToken()->getPayload()->get('career_id');

            return $value === null ? null : (int) $value;
        } catch (\Throwable) {
            return null;
        }
    }
}
