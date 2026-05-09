<?php

namespace Tests\Unit\WhiteBox;

use App\Http\Middleware\AuthenticateMobileApiToken;
use App\Http\Middleware\Role\EnsureUserHasRole;
use App\Models\MobileApiToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    use RefreshDatabase;

    // ── AuthenticateMobileApiToken ─────────────────────────────────────────

    public function test_mobile_api_middleware_returns_401_when_no_bearer_token(): void
    {
        $middleware = new AuthenticateMobileApiToken;
        $request = Request::create('/api/mobile/test', 'GET');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('No autorizado', $response->getContent());
    }

    public function test_mobile_api_middleware_returns_401_for_invalid_token(): void
    {
        $middleware = new AuthenticateMobileApiToken;
        $request = Request::create('/api/mobile/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalidtoken123');

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token inválido o expirado.', $data['message']);
    }

    public function test_mobile_api_middleware_returns_401_for_expired_token(): void
    {
        $user = User::factory()->create();
        $plainToken = bin2hex(random_bytes(32));

        MobileApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => Carbon::now()->subHour(),
            'last_used_at' => now(),
        ]);

        $middleware = new AuthenticateMobileApiToken;
        $request = Request::create('/api/mobile/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainToken);

        $response = $middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data);
    }

    public function test_mobile_api_middleware_authenticates_valid_token(): void
    {
        $user = User::factory()->create();
        $plainToken = bin2hex(random_bytes(32));

        MobileApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => null,
            'last_used_at' => now(),
        ]);

        $middleware = new AuthenticateMobileApiToken;
        $request = Request::create('/api/mobile/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainToken);

        $passed = false;
        $middleware->handle($request, function ($req) use (&$passed) {
            $passed = true;

            return new Response('ok');
        });

        $this->assertTrue($passed);
    }

    public function test_mobile_api_middleware_updates_last_used_at_on_valid_token(): void
    {
        $user = User::factory()->create();
        $plainToken = bin2hex(random_bytes(32));

        $token = MobileApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'Test',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => null,
            'last_used_at' => Carbon::now()->subDays(5),
        ]);

        $middleware = new AuthenticateMobileApiToken;
        $request = Request::create('/api/mobile/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$plainToken);

        $middleware->handle($request, fn () => new Response('ok'));

        $token->refresh();
        $this->assertTrue($token->last_used_at->isToday());
    }

    // ── EnsureUserHasRole ──────────────────────────────────────────────────

    public function test_ensure_user_has_role_aborts_403_when_unauthenticated(): void
    {
        $middleware = new EnsureUserHasRole;
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);

        $middleware->handle($request, fn () => new Response('ok'), 'administrador');
    }

    public function test_ensure_user_has_role_aborts_403_when_wrong_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('barbero');

        $middleware = new EnsureUserHasRole;
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        $middleware->handle($request, fn () => new Response('ok'), 'administrador');
    }

    public function test_ensure_user_has_role_passes_when_user_has_correct_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        $middleware = new EnsureUserHasRole;
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $passed = false;
        $middleware->handle($request, function () use (&$passed) {
            $passed = true;

            return new Response('ok');
        }, 'administrador');

        $this->assertTrue($passed);
    }

    public function test_ensure_user_has_role_passes_when_user_has_one_of_allowed_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole('recepcionista');

        $middleware = new EnsureUserHasRole;
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $passed = false;
        $middleware->handle($request, function () use (&$passed) {
            $passed = true;

            return new Response('ok');
        }, 'administrador', 'recepcionista');

        $this->assertTrue($passed);
    }
}
