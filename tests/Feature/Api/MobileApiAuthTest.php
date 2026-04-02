<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_token_and_me_returns_authenticated_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Administrador Mobile',
            'email' => 'mobile-admin@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'mobile-admin@example.com',
            'password' => 'password',
            'device_name' => 'iPhone 15',
        ]);

        $login->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure([
                'message',
                'token_type',
                'token',
                'user' => ['id', 'name', 'email', 'roles'],
            ]);

        $token = $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'mobile-admin@example.com');
    }
}