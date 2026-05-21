<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPMailer\PHPMailer\PHPMailer;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'id' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::find($credentials['id']);

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return response()->json(['error' => 'Credenciales inválidas'], 401);
            }

            if (!$user->activo) {
                return response()->json(['error' => 'Cuenta desactivada'], 403);
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user),
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
        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['message' => 'Sesión cerrada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo cerrar sesión'], 500);
        }
    }

    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json(['access_token' => $token, 'token_type' => 'bearer']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo refrescar token'], 500);
        }
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
                ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
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

            $sent = $this->sendPasswordResetToken($user, $token);
            if (!$sent) {
                return response()->json(['error' => 'No se pudo enviar el correo de recuperacion. Verifica la configuracion SMTP de Gmail en Railway.'], 500);
            }

            return response()->json(['message' => 'Token enviado al correo registrado.']);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
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

            $user->update(['password' => Hash::make($validated['password'])]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $jwt = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Contraseña actualizada correctamente.',
                'access_token' => $jwt,
                'token_type' => 'bearer',
                'user' => $this->userPayload($user),
            ]);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        }
    }

    private function validResetUser(string $id, string $email): ?User
    {
        return User::where('id', $id)
            ->whereRaw('LOWER(email) = ?', [strtolower($email)])
            ->where('activo', true)
            ->first();
    }

    private function validResetToken(string $email, string $token): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$record || !$record->created_at || Carbon::parse($record->created_at)->lt(now()->subMinutes(15))) {
            return false;
        }

        return Hash::check($token, $record->token);
    }

    private function sendPasswordResetToken(User $user, string $token): bool
    {
        if (!$user->email) {
            return false;
        }

        $name = trim("{$user->nombres} {$user->apa}");
        $html = view('emails.password-reset-token', [
            'name' => $name ?: $user->id,
            'token' => $token,
        ])->render();

        try {
            $mail = new PHPMailer(true);
            $smtp = config('mail.mailers.smtp');
            $username = (string) ($smtp['username'] ?? '');
            $password = (string) ($smtp['password'] ?? '');
            $fromAddress = (string) config('mail.from.address');
            $fromName = (string) config('mail.from.name');

            if (!$username || !$password || !$fromAddress) {
                return false;
            }

            $mail->isSMTP();
            $mail->Host = (string) ($smtp['host'] ?? 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS) ?: PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($smtp['port'] ?? 587);
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = (int) env('MAIL_TIMEOUT', 30);

            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($user->email, $name ?: $user->id);
            $mail->isHTML(true);
            $mail->Subject = 'Token de recuperacion de contrasena';
            $mail->Body = $html;
            $mail->AltBody = "Hola " . ($name ?: $user->id) . ", tu token de recuperacion es: {$token}. Vence en 15 minutos.";
            $mail->send();

            return true;
        } catch (\Throwable $e) {
            logger()->error('No se pudo enviar correo de recuperacion con PHPMailer.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'smtp_host' => $mail->Host ?? null,
                'smtp_port' => $mail->Port ?? null,
                'error' => $e->getMessage(),
            ]);
            report($e);
            return false;
        }
    }

    private function userPayload(User $user): array
    {
        $payload = $user->only(['id', 'nombres', 'apa', 'ama', 'email', 'perfil_id', 'semestre', 'grupo', 'photo_path', 'profile_completed_at']);
        $managerIds = collect(SystemSetting::valueFor('evaluation_manager_teacher_ids', []))->map(fn ($id) => (string) $id)->all();
        $payload['is_evaluation_manager'] = (int) $user->perfil_id === 1 || in_array((string) $user->id, $managerIds, true);

        return $payload;
    }
}
