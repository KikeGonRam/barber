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
 * API de configuración de la barbería (Fase 9.9): cubre datos_bancarios,
 * que el endpoint aceptaba en la web (UpdateBarbershopSettingRequest) pero
 * la API ignoraba silenciosamente — el cliente nunca veía la CLABE al pagar
 * por transferencia si se configuraba solo desde la API/Nuxt.
 */
class SettingApiTest extends TestCase
{
    private string $adminToken = 'test-setting-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Config API', 'email' => 'admin-config-api@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
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

    public function test_update_persists_and_returns_bank_details(): void
    {
        $response = $this->withToken($this->adminToken)->putJson('/api/v1/settings', [
            'nombre' => 'UrbanBlade Test', 'politica_cancelacion' => 24,
            'clabe' => '012345678901234567', 'banco' => 'BBVA', 'beneficiario' => 'UrbanBlade SA', 'concepto' => 'Corte',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.datos_bancarios.clabe', '012345678901234567')
            ->assertJsonPath('data.datos_bancarios.banco', 'BBVA');

        $this->withToken($this->adminToken)->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.datos_bancarios.beneficiario', 'UrbanBlade SA');
    }

    public function test_toggle_maintenance_flips_the_flag(): void
    {
        $this->withToken($this->adminToken)->postJson('/api/v1/settings/maintenance')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', true);

        $this->withToken($this->adminToken)->postJson('/api/v1/settings/maintenance')
            ->assertOk()
            ->assertJsonPath('data.maintenance_mode', false);
    }
}
