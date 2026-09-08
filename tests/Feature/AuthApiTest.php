<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Fase auth-pages-plan (frontend-urban): register()/forgotPassword()/
 * resetPassword() ya existían en AuthController pero nunca tenían cobertura
 * de test -- se agregan aquí junto con el fix de a dónde apunta el link de
 * recuperación (ver AppServiceProvider::boot(), ResetPassword::createUrlUsing()).
 */
class AuthApiTest extends TestCase
{
    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        Client::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        \DB::connection('mongodb')->table('password_reset_tokens')->delete();

        parent::tearDown();
    }

    public function test_register_creates_a_cliente_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Nuevo Cliente',
            'email' => 'nuevo-cliente-register@test.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['message', 'token_type', 'token', 'user']);
        $this->assertIsString($response->json('user.id'));
        $this->assertIsString($response->json('user.client_id'));
        $this->assertNull($response->json('user.barber_id'));

        $user = User::where('email', 'nuevo-cliente-register@test.local')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull(Client::where('user_id', (string) $user->id)->first());
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        User::create(['name' => 'Ya Existe', 'email' => 'duplicado@test.local', 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Otro',
            'email' => 'duplicado@test.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alguien',
            'email' => 'confirmacion-no-coincide@test.local',
            'password' => 'password123',
            'password_confirmation' => 'otra-cosa',
        ]);

        $response->assertStatus(422);
    }

    public function test_forgot_password_sends_a_reset_link_pointing_to_the_nuxt_frontend(): void
    {
        Notification::fake();
        $user = User::create(['name' => 'Recupera Cliente', 'email' => 'recupera@test.local', 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'recupera@test.local']);
        $response->assertOk();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $url = $mail->actionUrl;

            return str_starts_with($url, config('app.frontend_url').'/reset-password?token=')
                && str_contains($url, 'email=recupera%40test.local');
        });
    }

    public function test_forgot_password_does_not_reveal_whether_the_email_exists(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'no-existe-para-nada@test.local']);

        // El status de Laravel para "usuario no encontrado" en Password::sendResetLink()
        // no es RESET_LINK_SENT -> AuthController lo traduce a 400, mismo mensaje
        // genérico que un fallo real de envío (nunca "ese correo no existe").
        $response->assertStatus(400);
        $response->assertJsonMissingPath('email');
    }

    public function test_reset_password_with_a_valid_token_updates_the_password(): void
    {
        $user = User::create(['name' => 'Resetea Cliente', 'email' => 'resetea@test.local', 'password' => 'password-vieja']);
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'resetea@test.local',
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ]);

        $response->assertOk();

        $this->assertTrue(Hash::check('password-nueva-123', $user->fresh()->password));
    }

    public function test_reset_password_rejects_an_invalid_token(): void
    {
        User::create(['name' => 'Token Invalido', 'email' => 'token-invalido@test.local', 'password' => 'password']);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'un-token-que-no-existe',
            'email' => 'token-invalido@test.local',
            'password' => 'password-nueva-123',
            'password_confirmation' => 'password-nueva-123',
        ]);

        $response->assertStatus(400);
    }

    public function test_login_authenticates_verified_user_and_returns_token_and_user_resource(): void
    {
        $user = User::create([
            'name' => 'Login User',
            'email' => 'login-user@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->markEmailAsVerified();

        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user->assignRole($role);
        Client::create(['user_id' => (string) $user->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login-user@test.local',
            'password' => 'secret123',
            'device_name' => 'Test Mobile',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'token_type',
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'avatar_url',
                'roles',
                'profile_complete',
                'profile_missing',
                'client_id',
                'barber_id',
            ],
        ]);
        $this->assertNotEmpty($response->json('token'));
        $this->assertSame('login-user@test.local', $response->json('user.email'));
        $this->assertSame((string) $user->id, (string) $response->json('user.id'));
    }

    public function test_login_rejects_invalid_credentials_without_revealing_email_existence(): void
    {
        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing@test.local',
            'password' => Hash::make('correct-password'),
        ]);
        $user->markEmailAsVerified();

        // 1. Correo inexistente
        $responseNonExistent = $this->postJson('/api/v1/auth/login', [
            'email' => 'does-not-exist@test.local',
            'password' => 'any-password',
        ]);
        $responseNonExistent->assertStatus(422)
            ->assertJson(['message' => 'Las credenciales no son válidas.']);

        // 2. Correo existente pero contraseña errónea
        $responseWrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'existing@test.local',
            'password' => 'wrong-password',
        ]);
        $responseWrongPassword->assertStatus(422)
            ->assertJson(['message' => 'Las credenciales no son válidas.']);
    }

    public function test_login_rejects_unverified_user(): void
    {
        User::create([
            'name' => 'Unverified User',
            'email' => 'unverified@test.local',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'unverified@test.local',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Debes verificar tu correo para iniciar sesión.']);
    }

    public function test_me_returns_authenticated_user_resource(): void
    {
        $user = User::create([
            'name' => 'Me User',
            'email' => 'me-user@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->markEmailAsVerified();
        $role = Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        $user->assignRole($role);

        $issued = $user->issueMobileApiToken('Test Runner');

        $response = $this->withToken($issued['token'])->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
                'avatar_url',
                'roles',
                'profile_complete',
                'profile_missing',
                'client_id',
                'barber_id',
            ],
        ]);
        $this->assertSame('me-user@test.local', $response->json('user.email'));
    }

    public function test_logout_revokes_current_mobile_api_token(): void
    {
        $user = User::create([
            'name' => 'Logout User',
            'email' => 'logout-user@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->markEmailAsVerified();

        $issued = $user->issueMobileApiToken('Device To Logout');
        $tokenString = $issued['token'];
        $tokenId = (string) $issued['token_model']->id;

        $response = $this->withToken($tokenString)->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Sesión cerrada correctamente.']);

        $this->assertNull(MobileApiToken::find($tokenId));

        // Peticiones posteriores con token revocado deben fallar con 401
        $this->withToken($tokenString)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_refresh_token_issues_new_token_and_revokes_old_one(): void
    {
        $user = User::create([
            'name' => 'Refresh User',
            'email' => 'refresh-user@test.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->markEmailAsVerified();

        $issued = $user->issueMobileApiToken('Device To Refresh');
        $oldToken = $issued['token'];
        $oldTokenId = (string) $issued['token_model']->id;

        $response = $this->withToken($oldToken)->postJson('/api/v1/auth/refresh-token');

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'token_type',
            'token',
            'expires_at',
            'user' => [
                'id',
                'name',
                'email',
                'avatar_url',
                'roles',
            ],
        ]);

        $newToken = $response->json('token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);

        // El token anterior queda revocado
        $this->assertNull(MobileApiToken::find($oldTokenId));
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertStatus(401);

        // El nuevo token autentica exitosamente
        $this->withToken($newToken)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_unauthenticated_request_to_protected_auth_endpoints_returns_401(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
        $this->postJson('/api/v1/auth/refresh-token')->assertStatus(401);
    }
}
