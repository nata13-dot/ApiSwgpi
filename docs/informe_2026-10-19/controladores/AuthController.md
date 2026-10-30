# AuthController

Fuente: [app/Http/Controllers/API/AuthController.php](../../../app/Http/Controllers/API/AuthController.php). Corte: 19 de octubre de 2026.

## Rutas vinculadas

| Método | URI | Acción | Middleware declarado |
|---|---|---|---|
| GET\|HEAD | `/api/auth/careers` | [careers](#careers) | api, Authenticate:api, EnsureUserIsActive |
| POST | `/api/auth/heartbeat` | [heartbeat](#heartbeat) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/auth/login` | [login](#login) | api, ThrottleRequests:login |
| POST | `/api/auth/logout` | [logout](#logout) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| GET\|HEAD | `/api/auth/me` | [me](#me) | api, Authenticate:api, EnsureUserIsActive, ResolveCareerContext, EnsureCareerModuleEnabled, AuditCareerMutation, EnsureAcademicPeriodOpen |
| POST | `/api/auth/password/request-token` | [requestPasswordReset](#requestpasswordreset) | api, ThrottleRequests:password-recovery |
| POST | `/api/auth/password/reset` | [resetPasswordWithToken](#resetpasswordwithtoken) | api, ThrottleRequests:password-recovery |
| POST | `/api/auth/password/verify-token` | [verifyPasswordResetToken](#verifypasswordresettoken) | api, ThrottleRequests:password-recovery |
| POST | `/api/auth/refresh` | [refresh](#refresh) | api |
| POST | `/api/auth/switch-career` | [switchCareer](#switchcareer) | api, Authenticate:api, EnsureUserIsActive |

## Declaraciones y propiedades

Las propiedades, constantes, dependencias y comentarios se transcriben a continuación. Los métodos se separan después.

```php
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{



















}
```

## __construct

Visibilidad: `public`. Líneas originales 22–24.

```php
    public function __construct(private readonly CareerContext $careerContext)
    {
    }
```

## login

Visibilidad: `public`. Líneas originales 26–62.

Parámetros consultados directamente (no equivale a lista de campos obligatorios): `carrera_id`, `remember`.

```php
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'id' => 'required|string',
                'password' => 'required|string',
                'remember' => 'nullable|boolean',
                'carrera_id' => 'nullable|integer|exists:carreras,id',
            ]);

            $user = User::find($credentials['id']);

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            if (!$user->activo) {
                return response()->json(['error' => 'Cuenta desactivada'], 403);
            }

            $remember = $request->boolean('remember');
            $access = $this->resolveCareerAccess($user, $request->integer('carrera_id') ?: null);
            if (!$access) {
                return response()->json(['error' => 'La cuenta no tiene una carrera activa asignada.'], 403);
            }
            $token = $this->issueToken($user, $remember, $access['career']->id, $access['profile_id']);

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
```

## me

Visibilidad: `public`. Líneas originales 64–80.

```php
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
```

## careers

Visibilidad: `public`. Líneas originales 82–90.

```php
    public function careers()
    {
        $user = auth('api')->user();

        return response()->json([
            'careers' => $this->availableCareers($user),
            'active_career_id' => $this->careerContext->careerId() ?? $this->careerIdFromToken(),
        ]);
    }
```

## switchCareer

Visibilidad: `public`. Líneas originales 92–121.

```php
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
        $newToken = $this->issueToken($user, $remember, $access['career']->id, $access['profile_id']);
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
```

## logout

Visibilidad: `public`. Líneas originales 123–131.

```php
    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesión'], 500);
        }
    }
```

## refresh

Visibilidad: `public`. Líneas originales 133–157.

```php
    public function refresh()
    {
        try {
            $oldPayload = JWTAuth::parseToken()->getPayload();
            $careerId = $oldPayload->get('career_id');
            $remember = (bool) $oldPayload->get('remember');
            $token = JWTAuth::refresh(JWTAuth::getToken());
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
```

## heartbeat

Visibilidad: `public`. Líneas originales 159–165.

```php
    public function heartbeat()
    {
        return response()->json([
            'message' => 'Sesion activa',
            'server_time' => now()->toIso8601String(),
        ]);
    }
```

## requestPasswordReset

Visibilidad: `public`. Líneas originales 167–213.

```php
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
                return response()->json(['error' => 'El No. de Control, No. de empleado y el correo no coinciden con una cuenta activa.'], 422);
            }

            $token = (string) random_int(100000, 999999);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ]
            );

            Mail::to($user->email)->send(new PasswordResetTokenMail($user, $token));

            return response()->json(['message' => 'Token enviado al correo registrado.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            logger()->error('Error inesperado solicitando token de recuperacion.', [
                'mail_mailer' => config('mail.default'),
                'mail_from' => config('mail.from.address'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'No se pudo procesar la solicitud de recuperacion. Revisa los logs de la API en Railway.',
            ], 500);
        }
    }
```

## verifyPasswordResetToken

Visibilidad: `public`. Líneas originales 215–237.

```php
    public function verifyPasswordResetToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:10',
                'email' => 'required|email',
                'token' => 'required|string|size:6',
            ], [], [
                'id' => 'No. de Control, No. de empleado',
                'email' => 'correo',
                'token' => 'token',
            ]);

            $user = $this->validResetUser($validated['id'], $validated['email']);
            if (!$user || !$this->validResetToken($user->email, $validated['token'])) {
                return response()->json(['error' => 'El token no es valido o ya expiro.'], 422);
            }

            return response()->json(['message' => 'Token validado.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## resetPasswordWithToken

Visibilidad: `public`. Líneas originales 239–278.

```php
    public function resetPasswordWithToken(Request $request)
    {
        try {
            $validated = $request->validate([
                'id' => 'required|string|max:10',
                'email' => 'required|email',
                'token' => 'required|string|size:6',
                'password' => 'required|string|min:6|max:72|confirmed',
            ], [], [
                'id' => 'No. de Control, No. de empleado',
                'email' => 'correo',
                'token' => 'token',
                'password' => 'contraseña',
            ]);

            $user = $this->validResetUser($validated['id'], $validated['email']);
            if (!$user || !$this->validResetToken($user->email, $validated['token'])) {
                return response()->json(['error' => 'El token no es valido o ya expiro.'], 422);
            }

            $user->update(['contrasena' => Hash::make($validated['password'])]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $access = $this->resolveCareerAccess($user);
            if (!$access) {
                return response()->json(['error' => 'La cuenta no tiene una carrera activa asignada.'], 403);
            }
            $jwt = $this->issueToken($user, true, $access['career']->id, $access['profile_id']);

            return response()->json([
                'message' => 'Contraseña actualizada correctamente.',
                'access_token' => $jwt,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user, $access['career'], $access['profile_id']),
                'remember' => true,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }
```

## validResetUser

Visibilidad: `private`. Líneas originales 280–286.

```php
    private function validResetUser(string $id, string $email): ?User
    {
        return User::where('id', $id)
            ->whereRaw('LOWER(correo) = ?', [strtolower($email)])
            ->where('activo', true)
            ->first();
    }
```

## validResetToken

Visibilidad: `private`. Líneas originales 288–296.

```php
    private function validResetToken(string $email, string $token): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$record || !$record->created_at || Carbon::parse($record->created_at)->lt(now()->subMinutes(15))) {
            return false;
        }

        return Hash::check($token, $record->token);
    }
```

## userPayload

Visibilidad: `private`. Líneas originales 298–325.

```php
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
        ];
        $managerIds = collect(CareerSetting::valueForCareer($career?->id, 'evaluation_manager_teacher_ids', []))
            ->map(fn ($id) => (string) $id)
            ->all();
        $payload['is_evaluation_manager'] = in_array($activeProfileId, [1, 4], true)
            || in_array((string) $user->id, $managerIds, true);

        return $payload;
    }
```

## issueToken

Visibilidad: `private`. Líneas originales 327–334.

```php
    private function issueToken(User $user, bool $remember, int $careerId, int $profileId): string
    {
        return JWTAuth::claims([
            'remember' => $remember,
            'career_id' => $careerId,
            'career_role' => $profileId,
        ])->fromUser($user);
    }
```

## resolveCareerAccess

Visibilidad: `private`. Líneas originales 336–362.

```php
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
```

## availableCareers

Visibilidad: `private`. Líneas originales 364–387.

```php
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
```

## careerPayload

Visibilidad: `private`. Líneas originales 389–406.

```php
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
```

## rememberedToken

Visibilidad: `private`. Líneas originales 408–415.

```php
    private function rememberedToken(): bool
    {
        try {
            return (bool) JWTAuth::parseToken()->getPayload()->get('remember');
        } catch (\Throwable) {
            return false;
        }
    }
```

## careerIdFromToken

Visibilidad: `private`. Líneas originales 417–426.

```php
    private function careerIdFromToken(): ?int
    {
        try {
            $value = JWTAuth::parseToken()->getPayload()->get('career_id');

            return $value === null ? null : (int) $value;
        } catch (\Throwable) {
            return null;
        }
    }
```
