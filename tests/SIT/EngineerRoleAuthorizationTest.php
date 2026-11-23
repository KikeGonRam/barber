<?php

namespace Tests\SIT;

use App\Models\MobileApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Prueba de regresión tras la auditoría de funciones del 23-nov-2026: el dueño del proyecto decidió quitar
 * el rol "ingeniero" (ver config/features.php y el docblock de RolePermissionSeeder). RolePermissionSeeder ya
 * no siembra este rol ni le sincroniza permisos, pero Mongo puede tener una cuenta vieja que todavía lo tenga
 * en su campo role_id -- los seeders no borran datos existentes (ver CLAUDE.md). Este test simula justo esa
 * cuenta vieja (crea el Role 'ingeniero' a mano, como si ya existiera en la base, y un usuario con ese rol en
 * role_id) y comprueba que ya NO entra a ninguna de las rutas que antes sí: con ningún permiso vigente
 * sincronizado y con role.custom:administrador en cada grupo de rutas, debe recibir 403, nunca 200.
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

    /**
     * Simula una cuenta vieja de Mongo que todavía trae 'ingeniero' en role_id: el seeder ya no crea este
     * rol, así que se crea aquí a mano (firstOrCreate, sin sincronizar ningún permiso) tal como quedaría un
     * Role huérfano que nadie volvió a tocar.
     */
    private function legacyIngenieroToken(): string
    {
        $role = Role::firstOrCreate(['name' => 'ingeniero', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Ingeniero Legado', 'email' => 'ingeniero-legado@test.local', 'password' => 'password']);
        $user->forceFill(['email_verified_at' => now(), 'role_id' => [(string) $role->id]])->save();

        $plaintext = 'test-token-ingeniero-legado-'.uniqid();
        MobileApiToken::create([
            'user_id' => (string) $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plaintext),
        ]);

        return $plaintext;
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

    public function test_a_legacy_ingeniero_account_no_longer_reaches_any_route_it_used_to(): void
    {
        $token = $this->legacyIngenieroToken();

        // Antes de la auditoría, 'ingeniero' podía ver estas rutas de solo lectura (dashboards, reportes,
        // predicciones, estado del servidor). Ahora ningún grupo de rutas lo reconoce: 403 en todas.
        $this->getAs($token, '/api/v1/admin/system/status')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/dashboard/stats')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/dashboard/metrics')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/predictions/insights')->assertForbidden();
        $this->getAs($token, '/api/v1/reports')->assertForbidden();
        $this->getAs($token, '/api/v1/logs')->assertForbidden();
        $this->getAs($token, '/api/v1/admin/reports/peak-hours')->assertForbidden();

        // Nunca tuvo acceso a gestión de negocio, y sigue sin tenerlo.
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

    public function test_administrador_still_has_full_access_to_the_routes_ingeniero_used_to_share(): void
    {
        $token = $this->tokenForRole('administrador', 'admin-shared-routes@test.local');

        $this->getAs($token, '/api/v1/admin/system/status')->assertOk();
        $this->getAs($token, '/api/v1/admin/dashboard/stats')->assertOk();
        $this->getAs($token, '/api/v1/reports')->assertOk();
        $this->getAs($token, '/api/v1/logs')->assertOk();
    }

    public function test_other_roles_still_cannot_reach_the_system_status_route(): void
    {
        $recepcionistaToken = $this->tokenForRole('recepcionista', 'recep-system@test.local');
        $barberoToken = $this->tokenForRole('barbero', 'barbero-system@test.local');
        $clienteToken = $this->tokenForRole('cliente', 'cliente-system@test.local');

        $this->getAs($recepcionistaToken, '/api/v1/admin/system/status')->assertForbidden();
        $this->getAs($barberoToken, '/api/v1/admin/system/status')->assertForbidden();
        $this->getAs($clienteToken, '/api/v1/admin/system/status')->assertForbidden();
    }
}
