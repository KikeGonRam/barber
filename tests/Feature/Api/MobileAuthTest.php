<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class MobileAuthTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'token_type', 'token', 'user']);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'contraseña-incorrecta',
        ]);

        $response->assertStatus(422);
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_unverified_user_cannot_login(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_register_via_mobile_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente Móvil',
            'email' => 'movil@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'movil@example.com']);
    }

    public function test_register_requires_matching_password_confirmation(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Cliente Móvil',
            'email' => 'movil2@example.com',
            'password' => 'password',
            'password_confirmation' => 'otra-cosa',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_their_profile_with_a_valid_token(): void
    {
        $user = User::factory()->create();
        $token = $user->issueMobileApiToken()['token'];

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/auth/me');

        $response->assertOk();
    }

    public function test_me_requires_a_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_rejects_an_invalid_token(): void
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer token-invalido'])
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }
}
