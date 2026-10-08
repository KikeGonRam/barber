<?php

namespace App\Services\Push;

use Firebase\JWT\JWT;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Envío FCM HTTP v1; si no hay credenciales configuradas, no rompe la cola. */
class FcmPushService
{
    public function isConfigured(): bool
    {
        $path = (string) config('services.firebase.credentials');

        return filled(config('services.firebase.project_id')) && $path !== '' && is_readable($path);
    }

    /**
     * Envía el push al teléfono del usuario. Devuelve true si FCM lo aceptó. Si FCM responde que
     * el token ya no existe (app desinstalada o reinstalada), se borra para no seguir mandándole.
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendToUser(object $user, array $payload): bool
    {
        $token = (string) ($user->fcm_token ?? '');
        if ($token === '' || ! $this->isConfigured()) {
            return false;
        }

        try {
            $project = (string) config('services.firebase.project_id');
            $response = Http::withToken($this->accessToken())
                ->timeout(10)
                ->post("https://fcm.googleapis.com/v1/projects/{$project}/messages:send", [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => (string) ($payload['title'] ?? 'UrbanBlade'),
                            'body' => (string) ($payload['body'] ?? ''),
                        ],
                        'data' => collect($payload)->mapWithKeys(
                            fn ($value, $key) => [(string) $key => (string) $value]
                        )->all(),
                        'android' => [
                            'priority' => 'high',
                            'notification' => ['channel_id' => PushRouting::channel($payload['channel'] ?? null)],
                        ],
                    ],
                ]);

            if ($response->successful()) {
                return true;
            }

            if ($this->isDeadToken($response->status(), (array) $response->json())) {
                $this->forgetToken($user);
            }

            Log::warning('Fallo push FCM', [
                'user_id' => (string) ($user->id ?? ''),
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Fallo push FCM', [
                'user_id' => (string) ($user->id ?? ''),
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * FCM v1 marca un token muerto con 404 UNREGISTERED o 400 INVALID_ARGUMENT sobre el token.
     *
     * @param  array<mixed>  $body
     */
    private function isDeadToken(int $status, array $body): bool
    {
        $codes = collect((array) data_get($body, 'error.details', []))->pluck('errorCode')->filter()->all();

        return $status === 404 || in_array('UNREGISTERED', $codes, true)
            || ($status === 400 && in_array('INVALID_ARGUMENT', $codes, true));
    }

    private function forgetToken(object $user): void
    {
        if ($user instanceof Model) {
            $user->forceFill(['fcm_token' => null])->save();
        }
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase:fcm-access-token', now()->addMinutes(50), function (): string {
            $credentials = json_decode(
                (string) file_get_contents((string) config('services.firebase.credentials')),
                true,
                flags: JSON_THROW_ON_ERROR
            );
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            return (string) Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ])->throw()->json('access_token');
        });
    }
}
