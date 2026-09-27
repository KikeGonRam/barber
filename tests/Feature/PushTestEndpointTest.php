<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\User;
use App\Services\Push\FcmPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * T061 (HT-21): push de prueba al propio teléfono y limpieza de tokens que FCM ya no reconoce.
 */
class PushTestEndpointTest extends TestCase
{
    private ?string $credentialsPath = null;

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::query()->delete();
        if ($this->credentialsPath) {
            @unlink($this->credentialsPath);
        }
        Cache::forget('firebase:fcm-access-token');

        parent::tearDown();
    }

    private function configureFirebase(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKey);

        $this->credentialsPath = (string) tempnam(sys_get_temp_dir(), 'urbanblade-fcm-');
        file_put_contents($this->credentialsPath, json_encode(['client_email' => 'firebase-admin@urbanblade.test', 'private_key' => $privateKey], JSON_THROW_ON_ERROR));
        config()->set('services.firebase.project_id', 'urbanblade-test');
        config()->set('services.firebase.credentials', $this->credentialsPath);
        Cache::forget('firebase:fcm-access-token');
    }

    /** @return array{0: User, 1: string} */
    private function userWithToken(?string $fcmToken): array
    {
        $user = User::create(['name' => 'Push', 'email' => Str::uuid().'@test.local', 'password' => 'password', 'fcm_token' => $fcmToken]);
        $plain = 'token-'.Str::random(40);
        MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $plain), 'expires_at' => now()->addDay()]);

        return [$user, $plain];
    }

    public function test_the_test_push_goes_only_to_the_callers_own_phone(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'messages/1']),
        ]);
        [, $token] = $this->userWithToken('mi-telefono');
        $this->userWithToken('telefono-de-otro');

        $this->withToken($token)->postJson('/api/v1/profile/push-test')
            ->assertOk()
            ->assertJsonPath('message', 'Notificación de prueba enviada.');

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'messages:send') && $r['message']['token'] === 'mi-telefono');
        Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), 'messages:send') && $r['message']['token'] === 'telefono-de-otro');
    }

    public function test_without_a_registered_phone_it_explains_why(): void
    {
        $this->configureFirebase();
        [, $token] = $this->userWithToken(null);

        $this->withToken($token)->postJson('/api/v1/profile/push-test')->assertStatus(422);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/v1/profile/push-test')->assertUnauthorized();
    }

    public function test_a_token_fcm_no_longer_recognizes_is_cleared(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'oauth-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404),
        ]);
        [$user] = $this->userWithToken('token-viejo');

        $this->assertFalse(app(FcmPushService::class)->sendToUser($user, ['title' => 'Hola', 'body' => 'Prueba']));
        $this->assertNull($user->fresh()?->getAttribute('fcm_token'));
    }
}
