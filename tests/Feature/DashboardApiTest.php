<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Client;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. Cubre el endpoint
 * GET /api/v1/dashboard usado por el frontend Nuxt (ver
 * frontend-urban/.claude/skills/nuxt-migration-plan/SKILL.md, Fases 4-5): el
 * mismo shape curado (kpis/next.../sparkHighlights, etc.) que las vistas
 * Inertia de recepcionista/barbero, ahora servido como JSON vía token
 * Bearer en vez de props de Inertia.
 */
class DashboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        // mongo-test es un contenedor persistente en local (a diferencia de
        // CI, que arranca un mongo:7 efímero en cada corrida) — sin esta
        // limpieza, tokens/usuarios con el mismo email/hash se acumulan
        // entre corridas locales y el token_hash duplicado hace que el
        // middleware resuelva al usuario equivocado (o ninguno), causando
        // 401 en corridas repetidas. Ya pasó una vez, ver el commit que
        // agregó este tearDown.
        Barber::query()->delete();
        Client::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
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

    public function test_barbero_gets_the_curated_dashboard_payload(): void
    {
        $role = Role::where('name', 'barbero')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Barbero API', 'email' => 'barbero-api-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        Barber::create(['user_id' => (string) $user->id, 'nombre' => 'Barbero API', 'activo' => true]);

        $token = 'test-plaintext-token-barber-dashboard';
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJson(['role' => 'barbero']);
        $response->assertJsonStructure([
            'role',
            'data' => ['todayLabel', 'kpis', 'performanceChart', 'servicesChart', 'barberToday', 'barberPending', 'sparkHighlights'],
        ]);
    }

    public function test_cliente_gets_the_curated_dashboard_payload(): void
    {
        $role = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Cliente API', 'email' => 'cliente-api-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        Client::create(['user_id' => (string) $user->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);

        $token = 'test-plaintext-token-client-dashboard';
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJson(['role' => 'cliente']);
        $response->assertJsonStructure([
            'role',
            'data' => ['todayLabel', 'kpis', 'nextAppointment', 'visitChart', 'loyalty', 'member', 'recommendation'],
        ]);
    }

    public function test_administrador_gets_the_curated_dashboard_payload(): void
    {
        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => 'Admin API', 'email' => 'admin-api-dash@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $token = 'test-plaintext-token-admin-dashboard';
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $token),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard');

        $response->assertOk();
        $response->assertJson(['role' => 'administrador']);
        $response->assertJsonStructure([
            'role',
            'data' => [
                'todayLabel', 'kpis', 'incomeChart', 'servicesChart', 'barberPerformance',
                'clientTrends', 'chatbotTelemetry', 'todayAppointments', 'recentAppointments',
                'insights', 'sparkHighlights',
            ],
        ]);
    }

    public function test_dashboard_endpoint_requires_a_token(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertUnauthorized();
    }
}
