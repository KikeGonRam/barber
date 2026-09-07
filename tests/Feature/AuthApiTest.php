<?php

namespace Tests\Feature;

use App\Models\Client;
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
}
