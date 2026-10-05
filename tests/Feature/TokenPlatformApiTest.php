<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Los tokens de web y móvil son distintos: cada uno declara su plataforma, caduca según
 * ella (config/auth.php: api_token_ttl_days) y la ventana se renueva sola al usarse.
 * Antes el login emitía tokens sin caducidad y web y móvil eran indistinguibles.
 */
class TokenPlatformApiTest extends TestCase
{
    private const ME_URL = '/api/v1/auth/me';

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.api_token_ttl_days' => ['web' => 30, 'movil' => 180]]);
        User::create(['name' => 'Cliente Token', 'email' => 'token-plataforma@test.local', 'password' => 'password123'])
            ->forceFill(['email_verified_at' => now()])->save();
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        Client::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();

        parent::tearDown();
    }

    private function login(array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/login', array_merge([
            'email' => 'token-plataforma@test.local', 'password' => 'password123',
        ], $extra));
    }

    private function lastToken(): MobileApiToken
    {
        return MobileApiToken::query()->latest('created_at')->firstOrFail();
    }

    public function test_a_web_login_gets_the_web_ttl(): void
    {
        $response = $this->login(['plataforma' => 'web', 'device_name' => 'Nuxt Web']);

        $response->assertOk();
        $token = $this->lastToken();
        $this->assertSame('web', $token->plataforma);
        $this->assertEqualsWithDelta(30, now()->diffInDays($token->expires_at, true), 0.01);
        $this->assertSame($token->expires_at->toISOString(), $response->json('expires_at'));
    }

    public function test_a_mobile_login_gets_the_mobile_ttl(): void
    {
        $this->login(['plataforma' => 'movil', 'device_name' => 'Pixel 8'])->assertOk();

        $token = $this->lastToken();
        $this->assertSame('movil', $token->plataforma);
        $this->assertEqualsWithDelta(180, now()->diffInDays($token->expires_at, true), 0.01);
    }

    public function test_the_platform_is_inferred_from_the_device_name_when_the_client_does_not_declare_it(): void
    {
        // Compatibilidad: la app Android actual no manda `plataforma`.
        $this->login(['device_name' => 'Android UrbanBlade'])->assertOk();
        $this->assertSame('movil', $this->lastToken()->plataforma);

        MobileApiToken::query()->delete();
        $this->login(['device_name' => 'Nuxt Web'])->assertOk();
        $this->assertSame('web', $this->lastToken()->plataforma);
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $this->login(['plataforma' => 'escritorio'])->assertUnprocessable()->assertJsonValidationErrors('plataforma');
    }

    public function test_using_a_token_renews_a_window_that_is_less_than_half_left(): void
    {
        $token = $this->tokenWith('web', now()->addDays(5));

        $this->withHeader('Authorization', "Bearer {$token['plain']}")->getJson(self::ME_URL)->assertOk();

        $this->assertEqualsWithDelta(30, now()->diffInDays($token['model']->fresh()->expires_at, true), 0.01);
    }

    public function test_using_a_token_with_plenty_of_time_left_does_not_rewrite_the_expiry(): void
    {
        $expiry = now()->addDays(29)->startOfSecond();
        $token = $this->tokenWith('web', $expiry);

        $this->withHeader('Authorization', "Bearer {$token['plain']}")->getJson(self::ME_URL)->assertOk();

        $this->assertSame($expiry->toISOString(), $token['model']->fresh()->expires_at->toISOString());
    }

    public function test_a_legacy_token_without_expiry_or_platform_is_classified_and_given_a_window_on_first_use(): void
    {
        $plain = 'token-heredado-sin-caducidad';
        $user = User::where('email', 'token-plataforma@test.local')->firstOrFail();
        $legacy = MobileApiToken::create(['user_id' => (string) $user->id, 'name' => 'Nuxt Web', 'token_hash' => hash('sha256', $plain)]);
        $this->assertNull($legacy->expires_at);

        $this->withHeader('Authorization', "Bearer {$plain}")->getJson(self::ME_URL)->assertOk();

        $legacy = $legacy->fresh();
        $this->assertSame('web', $legacy->plataforma);
        $this->assertEqualsWithDelta(30, now()->diffInDays($legacy->expires_at, true), 0.01);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $token = $this->tokenWith('movil', now()->subMinute());

        $this->withHeader('Authorization', "Bearer {$token['plain']}")->getJson(self::ME_URL)->assertUnauthorized();
    }

    public function test_refreshing_keeps_the_platform_and_its_window(): void
    {
        $token = $this->tokenWith('web', now()->addDays(3));

        $response = $this->withHeader('Authorization', "Bearer {$token['plain']}")->postJson('/api/v1/auth/refresh-token');

        $response->assertOk();
        $new = $this->lastToken();
        $this->assertSame('web', $new->plataforma);
        $this->assertEqualsWithDelta(30, now()->diffInDays($new->expires_at, true), 0.01);
    }

    public function test_logging_out_of_one_platform_leaves_the_other_session_alive(): void
    {
        $web = $this->tokenWith('web', now()->addDays(30));
        $movil = $this->tokenWith('movil', now()->addDays(180));

        $this->withHeader('Authorization', "Bearer {$web['plain']}")->postJson('/api/v1/auth/logout')->assertOk();

        $this->withHeader('Authorization', "Bearer {$web['plain']}")->getJson(self::ME_URL)->assertUnauthorized();
        $this->withHeader('Authorization', "Bearer {$movil['plain']}")->getJson(self::ME_URL)->assertOk();
    }

    /** @return array{plain: string, model: MobileApiToken} */
    private function tokenWith(string $plataforma, Carbon $expiresAt): array
    {
        $user = User::where('email', 'token-plataforma@test.local')->firstOrFail();
        $issued = $user->issueMobileApiToken('Dispositivo de prueba', ['*'], $expiresAt, $plataforma);

        return ['plain' => $issued['token'], 'model' => $issued['token_model']];
    }
}
