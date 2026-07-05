<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
    }

    public function test_first_registered_user_becomes_administrador(): void
    {
        $response = $this->post('/register', [
            'name' => 'Primer Usuario',
            'email' => 'primero@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'primero@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('administrador'));
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_subsequent_registered_users_become_cliente_with_profile(): void
    {
        User::factory()->create();

        $this->post('/register', [
            'name' => 'Cliente Nuevo',
            'email' => 'clientenuevo@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'clientenuevo@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull($user->clientProfile);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->post('/register', [
            'name' => 'Alguien',
            'email' => 'alguien@example.com',
            'password' => 'password',
            'password_confirmation' => 'diferente',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_requires_unique_email(): void
    {
        $existing = User::factory()->create();

        $response = $this->post('/register', [
            'name' => 'Duplicado',
            'email' => $existing->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
