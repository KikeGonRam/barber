<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el endpoint
 * GET /api/v1/dashboard usado por el frontend Nuxt (ver
 * frontend-urban/.claude/skills/nuxt-migration-plan/SKILL.md, Fase 4): el
 * mismo shape curado (kpis/nextAppointments/pendingOrders/flowChart/
 * sparkHighlights) que la versión Inertia de recepcionista, ahora servido
 * como JSON vía token Bearer en vez de props de Inertia.
 */
class DashboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_recepcionista_gets_the_curated_dashboard_payload(): void
    {
        $role = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Recepcionista API', 'email' => 'recepcion-api-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = 'test-plaintext-token-dashboard';
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJson(['role' => 'recepcionista']);
        $response->assertJsonStructure([
            'role',
            'data' => ['todayLabel', 'kpis', 'nextAppointments', 'pendingOrders', 'flowChart', 'sparkHighlights'],
        ]);
    }

    public function test_dashboard_endpoint_requires_a_token(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertUnauthorized();
    }
}
