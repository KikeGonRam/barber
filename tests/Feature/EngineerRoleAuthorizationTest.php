<?php

namespace Tests\Feature;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cubre el límite de acceso del rol "ingeniero" (Fase 3 del plan Stripe+ops-
 * role): debe poder ver dashboards/reportes/predicciones/estado del
 * servidor, pero nunca gestionar nada de negocio (usuarios, clientes,
 * inventario, barberos, servicios, configuración). Cada uno de los
 * controladores tocados en esta fase (DashboardAdminController,
 * ReportAdminController, PredictionController, ReportController,
 * LogController) tiene su propio guard interno además del middleware de
 * ruta -- este test prueba el camino real de punta a punta (HTTP + Bearer
 * token), no solo que el middleware de la ruta esté bien escrito.
 */
class EngineerRoleAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        MobileApiToken::query()->delete();
        User::withTrashed()->forceDelete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function tokenForRole(string $roleName, string $email): string
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user = User::create(['name' => ucfirst($roleName), 'email' => $email, 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plaintext = 'test-token-'.$roleName.'-'.uniqid();
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
    }

    private function getAs(string $token, string $uri)
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->getJson($uri);
    }

    public function test_ingeniero_can_reach_every_read_only_dashboard_and_report_route(): void
    {
        $token = $this->tokenForRole('ingeniero', 'ingeniero-allowed@test.local');

        $this->getAs($token, '/api/v1/admin/system/status')->assertOk();
        $this->getAs($token, '/api/v1/admin/dashboard/stats')->assertOk();
        $this->getAs($token, '/api/v1/admin/dashboard/metrics')->assertOk();
        $this->getAs($token, '/api/v1/admin/predictions/insights')->assertOk();
        $this->getAs($token, '/api/v1/admin/reports/list')->assertOk();
        $this->getAs($token, '/api/v1/reports')->assertOk();
        $this->getAs($token, '/api/v1/logs')->assertOk();
    }

    public function test_ingeniero_cannot_manage_any_business_resource(): void
    {
        $token = $this->tokenForRole('ingeniero', 'ingeniero-forbidden@test.local');

        // Usuarios, clientes, barberos, servicios, inventario, configuración,
        // campañas, sorteos y reseñas: nada de esto es "reportes/estado del
        // servidor" -- son las rutas que motivaron la corrección explícita
        // del alcance de este rol (nunca superset de administrador).
        $this->getAs($token, '/api/v1/users')->assertForbidden();
        $this->getAs($token, '/api/v1/clients')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/clients')->assertForbidden();
        $this->getAs($token, '/api/v1/barbers/manage')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/barbers')->assertForbidden();
        $this->getAs($token, '/api/v1/services/manage')->assertForbidden();
        $this->getAs($token, '/api/v1/inventory/products')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/inventory/products')->assertForbidden();
        $this->getAs($token, '/api/v1/settings')->assertForbidden();
        $this->getAs($token, '/api/v1/campaigns')->assertForbidden();
        $this->getAs($token, '/api/v1/raffles')->assertForbidden();
        $this->getAs($token, '/api/v1/reviews')->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/users', ['name' => 'x', 'email' => 'x@test.local'])
            ->assertForbidden();
    }

    public function test_administrador_still_has_full_access_to_the_routes_ingeniero_now_shares(): void
    {
        $token = $this->tokenForRole('administrador', 'admin-shared-routes@test.local');

        $this->getAs($token, '/api/v1/admin/system/status')->assertOk();
        $this->getAs($token, '/api/v1/admin/dashboard/stats')->assertOk();
        $this->getAs($token, '/api/v1/reports')->assertOk();
        $this->getAs($token, '/api/v1/logs')->assertOk();
    }

    public function test_other_roles_cannot_reach_the_new_system_status_route(): void
    {
        $recepcionistaToken = $this->tokenForRole('recepcionista', 'recep-system@test.local');
        $barberoToken = $this->tokenForRole('barbero', 'barbero-system@test.local');
        $clienteToken = $this->tokenForRole('cliente', 'cliente-system@test.local');

        $this->getAs($recepcionistaToken, '/api/v1/admin/system/status')->assertForbidden();
        $this->getAs($barberoToken, '/api/v1/admin/system/status')->assertForbidden();
        $this->getAs($clienteToken, '/api/v1/admin/system/status')->assertForbidden();
    }
}
