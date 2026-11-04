<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Envía notificaciones al teléfono con Firebase Cloud Messaging (API HTTP v1). Nada del sistema la llama
 * todavía: qué se notifica y a quién se decidirá aparte. La llave de la cuenta de servicio vive sólo en el
 * servidor, fuera de Git (FIREBASE_CREDENTIALS).
 */
class PushNotificationService
{
    public function configured(): bool
    {
        $path = (string) config('services.firebase.credentials');

        return $path !== '' && is_readable($path);
    }

    public function registerDevice(string $userId, string $token, string $platform = 'android'): void
    {
        DB::table('dispositivos_push')->updateOrInsert(
            ['token_hash' => hash('sha256', $token)],
            ['usuario_id' => $userId, 'token' => $token, 'plataforma' => $platform, 'actualizado_en' => now(), 'creado_en' => now()],
        );
    }

    public function removeDevice(string $userId, string $token): void
    {
        DB::table('dispositivos_push')->where('usuario_id', $userId)->where('token_hash', hash('sha256', $token))->delete();
    }

    /**
     * @param array<string, string> $data datos extra; "url" es la pantalla que abre al tocarla
     * @return array{sent: int, failed: int, removed: int}
     */
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'removed' => 0];
        if (!$this->configured()) {
            return $result;
        }
        foreach (DB::table('dispositivos_push')->where('usuario_id', $userId)->get(['id', 'token']) as $device) {
            $response = $this->send($device->token, $title, $body, $data);
            if ($response->successful()) {
                $result['sent']++;
            } elseif ($response->status() === 404 || $response->json('error.status') === 'UNREGISTERED') {
                // La app se desinstaló o el token cambió: ya no sirve.
                DB::table('dispositivos_push')->where('id', $device->id)->delete();
                $result['removed']++;
            } else {
                // Nunca se registra el contenido de la notificación ni el token.
                logger()->warning('Firebase rechazó una notificación.', ['status' => $response->status(), 'error' => $response->json('error.status')]);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function send(string $token, string $title, string $body, array $data)
    {
        $projectId = $this->credentials()['project_id'];

        return Http::withToken($this->accessToken())->acceptJson()->timeout(10)->connectTimeout(5)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", ['message' => [
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => array_map('strval', $data),
                'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'sgpi']],
            ]]);
    }

    private function credentials(): array
    {
        return json_decode((string) file_get_contents((string) config('services.firebase.credentials')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** Token de acceso de Google firmado con la cuenta de servicio; vale una hora. */
    private function accessToken(): string
    {
        return Cache::remember('firebase-access-token', now()->addMinutes(50), function () {
            $account = $this->credentials();
            $encode = fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
            $now = time();
            $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$encode(json_encode([
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));
            if (!openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new \RuntimeException('No se pudo firmar con la cuenta de servicio de Firebase.');
            }

            return Http::asForm()->timeout(10)->connectTimeout(5)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ])->throw()->json('access_token');
        });
    }
}
