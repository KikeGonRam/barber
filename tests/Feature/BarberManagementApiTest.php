<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. GET/PUT barbers/manage
 * (App\Http\Controllers\Api\Barber\BarberManagementController) es la API
 * que consume la nueva pantalla de administración de barberos en
 * frontend-urban (pages/barbers/manage/index.vue, 2026-09-10) -- hasta
 * ahora solo tenía cobertura del 403 para roles no-admin
 * (EngineerRoleAuthorizationTest), sin ningún camino exitoso probado.
 */
class BarberManagementApiTest extends TestCase
{
    private string $adminToken = 'test-barber-mgmt-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $admin = User::create(['name' => 'Admin Gestión Barberos', 'email' => 'admin-barber-mgmt@test.local', 'password' => 'password']);
        $admin->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();
        MobileApiToken::create(['user_id' => (string) $admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->adminToken)]);
    }

    protected function tearDown(): void
    {
        Barber::query()->delete();
        MobileApiToken::query()->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function makeBarber(string $name, string $email, bool $activo = true): Barber
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'password']);

        return Barber::create(['user_id' => (string) $user->id, 'nombre' => $name, 'activo' => $activo]);
    }

    public function test_index_lists_barbers_with_user_data(): void
    {
        $this->makeBarber('Ana Torres', 'ana-torres-'.Str::uuid().'@test.local');
        $this->makeBarber('Luis Prado', 'luis-prado-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->getJson('/api/v1/barbers/manage');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.user.name', 'Luis Prado'); // latest('id') primero
        $response->assertJsonStructure(['data' => [['id', 'especialidades', 'descripcion', 'foto', 'activo', 'user' => ['id', 'name', 'email']]], 'meta', 'filters']);
    }

    public function test_index_filters_by_search_and_active_status(): void
    {
        $this->makeBarber('Carlos Vega', 'carlos-vega-'.Str::uuid().'@test.local', true);
        $this->makeBarber('Diego Ruiz', 'diego-ruiz-'.Str::uuid().'@test.local', false);

        $bySearch = $this->withToken($this->adminToken)->getJson('/api/v1/barbers/manage?q=Carlos');
        $bySearch->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.name', 'Carlos Vega');

        $byActive = $this->withToken($this->adminToken)->getJson('/api/v1/barbers/manage?activo=0');
        $byActive->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.name', 'Diego Ruiz');
    }

    public function test_update_persists_profile_and_linked_user_fields(): void
    {
        $barber = $this->makeBarber('Nombre Viejo', 'viejo-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->putJson('/api/v1/barbers/manage/'.$barber->slug, [
            'name' => 'Nombre Nuevo',
            'email' => 'nuevo-'.Str::uuid().'@test.local',
            'especialidades' => 'Fades, barba',
            'descripcion' => 'Especialista en degradados.',
            'foto' => 'https://example.com/foto.jpg',
            'activo' => false,
            'comision_pct' => 45,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.user.name', 'Nombre Nuevo');
        $response->assertJsonPath('data.especialidades', 'Fades, barba');
        $response->assertJsonPath('data.activo', false);
        $response->assertJsonPath('data.comision_pct', 45);

        $fresh = $barber->fresh();
        $this->assertSame('Fades, barba', $fresh->especialidades);
        $this->assertFalse((bool) $fresh->activo);
        $this->assertSame('Nombre Nuevo', $fresh->user->name);
        $this->assertSame(45.0, (float) $fresh->comision_pct);
    }

    public function test_update_requires_name_and_a_valid_unique_email(): void
    {
        $barber = $this->makeBarber('Barbero Validación', 'validacion-'.Str::uuid().'@test.local');
        $other = $this->makeBarber('Otro Barbero', 'otro-'.Str::uuid().'@test.local');

        $response = $this->withToken($this->adminToken)->putJson('/api/v1/barbers/manage/'.$barber->slug, [
            'name' => '',
            'email' => $other->user->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_non_admin_cannot_reach_barber_management(): void
    {
        $barbero = $this->makeBarber('Barbero Regular', 'regular-'.Str::uuid().'@test.local');
        $token = 'test-non-admin-token';
        MobileApiToken::create(['user_id' => $barbero->user_id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/v1/barbers/manage')->assertForbidden();
        $this->withToken($token)->putJson('/api/v1/barbers/manage/'.$barbero->slug, ['name' => 'x', 'email' => 'x@test.local'])->assertForbidden();
    }
}
