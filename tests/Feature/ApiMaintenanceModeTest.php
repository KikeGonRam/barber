<?php

namespace Tests\Feature;

use App\Models\BarbershopSetting;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * App\Http\Middleware\Api\CheckApiMaintenanceMode: equivalente de
 * CheckMaintenanceMode (Blade) para la API. Antes de esto, activar "Modo
 * mantenimiento" desde frontend-urban (POST /settings/maintenance) no tenía
 * ningún efecto real -- nada en routes/api.php lo verificaba, así que
 * cualquier usuario seguía usando el sistema con normalidad. Regresión:
 * confirma que ahora sí bloquea con 503 a quien no sea administrador, sin
 * afectar en ningún caso al propio administrador ni al catálogo público.
 */
class ApiMaintenanceModeTest extends TestCase
{
    private string $adminToken = 'test-maintenance-admin-token';

    private string $clienteToken = 'test-maintenance-cliente-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $adminRole = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Mantenimiento', 'email' => 'admin-maintenance@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $adminRole->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);

        $clienteRole = Role::where('name', 'cliente')->where('guard_name', 'web')->firstOrFail();
        $cliente = User::create(['name' => 'Cliente Mantenimiento', 'email' => 'cliente-maintenance@test.local', 'password' => 'password']);
        $cliente->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $clienteRole->id]])->save();
        MobileApiToken::create(['user_id' => (string) $cliente->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->clienteToken)]);
    }

    protected function tearDown(): void
    {
        BarbershopSetting::query()->delete();
        Cache::forget('barbershop_setting');
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function enableMaintenance(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/settings/maintenance')
            ->assertOk()->assertJsonPath('data.maintenance_mode', true);
    }

    public function test_non_admin_is_blocked_with_503_while_maintenance_is_active(): void
    {
        $this->enableMaintenance();

        $response = $this->withToken($this->clienteToken)->getJson('/api/v1/profile');

        $response->assertStatus(503)->assertJsonPath('maintenance', true);
    }

    public function test_admin_is_never_blocked_even_while_maintenance_is_active(): void
    {
        $this->enableMaintenance();

        $this->withToken($this->adminToken)->getJson('/api/v1/profile')->assertOk();

        // El admin también puede seguir apagando el mantenimiento.
        $this->withToken($this->adminToken)->postJson('/api/v1/settings/maintenance')
            ->assertOk()->assertJsonPath('data.maintenance_mode', false);
    }

    public function test_public_catalog_stays_reachable_during_maintenance_even_for_a_guest(): void
    {
        $this->enableMaintenance();

        $this->getJson('/api/v1/services')->assertOk();
    }

    public function test_requests_pass_through_normally_when_maintenance_is_off(): void
    {
        $this->withToken($this->clienteToken)->getJson('/api/v1/profile')->assertOk();
    }
}
