<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * auth-polish-plan: login social vía Google. Sin GOOGLE_CLIENT_ID/SECRET
 * reales, redirect() responde 503 en vez de romper -- eso también se cubre
 * aquí. callback() usa Socialite::fake() (no le pega de verdad a Google).
 */
class SocialAuthApiTest extends TestCase
{
    protected function tearDown(): void
    {
        Client::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();

        parent::tearDown();
    }

    private function fakeGoogleUser(string $email, string $name = 'Cliente Google'): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->id = 'google-'.md5($email);
        $user->email = $email;
        $user->name = $name;
        $user->avatar = 'https://lh3.googleusercontent.com/a/test-avatar';

        return $user;
    }

    public function test_redirect_returns_503_without_configured_credentials(): void
    {
        Config::set('services.google.client_id', null);

        $response = $this->get('/api/v1/auth/google/redirect');

        $response->assertStatus(503);
    }

    public function test_redirect_sends_the_user_to_google_when_configured(): void
    {
        Config::set('services.google.client_id', 'fake-client-id');
        Socialite::fake('google');

        $response = $this->get('/api/v1/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringContainsString('socialite.fake/google', $response->headers->get('Location'));
    }

    public function test_callback_creates_a_new_cliente_user_and_redirects_with_a_token(): void
    {
        Socialite::fake('google', $this->fakeGoogleUser('nuevo-via-google@test.local', 'Nuevo Via Google'));

        $response = $this->get('/api/v1/auth/google/callback');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(config('app.frontend_url').'/auth/callback?token=', $location);

        $user = User::where('email', 'nuevo-via-google@test.local')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('https://lh3.googleusercontent.com/a/test-avatar', $user->avatar_url);
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertFalse($user->profileCompletion()['complete']);
        $this->assertNotNull(Client::where('user_id', (string) $user->id)->first());
    }

    public function test_callback_logs_in_an_existing_user_without_duplicating_it(): void
    {
        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $existing = User::create(['name' => 'Ya Existe', 'email' => 'ya-existe-google@test.local', 'password' => 'password']);
        $existing->assignRole($role);

        Socialite::fake('google', $this->fakeGoogleUser('ya-existe-google@test.local'));

        $response = $this->get('/api/v1/auth/google/callback');

        $response->assertRedirect();
        $this->assertSame(1, User::where('email', 'ya-existe-google@test.local')->count());
    }

    public function test_callback_redirects_to_login_with_an_error_when_google_fails(): void
    {
        Socialite::fake('google', function () {
            throw new \Exception('invalid_state');
        });

        $response = $this->get('/api/v1/auth/google/callback');

        $response->assertRedirect(config('app.frontend_url').'/login?error=google_failed');
    }
}
