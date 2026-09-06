<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El rol "ingeniero" existe para alguien de sistemas que necesita ver
 * estado del servidor/analitica/reportes sin poder gestionar nada de
 * negocio -- si alguna vez se le agrega un permiso *.gestionar por error
 * (p. ej. copiando la lista de administrador), este test lo agarra antes
 * de que llegue a producción. Decisión explícita del dueño del proyecto
 * (2026-09-06): nunca debe ser superset de administrador.
 */
class RolePermissionSeederTest extends TestCase
{
    protected function tearDown(): void
    {
        Role::query()->delete();
        Permission::query()->delete();
        \DB::connection('mongodb')->table(config('permission.table_names.role_has_permissions'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    public function test_ingeniero_role_is_read_only(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $ingeniero = Role::where('name', 'ingeniero')->where('guard_name', 'web')->firstOrFail();
        $permissions = $ingeniero->permissions->pluck('name')->all();

        sort($permissions);
        $this->assertSame(['logs.ver', 'reportes.ver', 'sistema.ver'], $permissions);

        foreach ($permissions as $permission) {
            $this->assertStringEndsNotWith('.gestionar', $permission, "ingeniero nunca debe tener el permiso de gestión '{$permission}'");
        }
    }

    public function test_ingeniero_permissions_are_a_subset_of_administrador(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $ingeniero = Role::where('name', 'ingeniero')->where('guard_name', 'web')->firstOrFail();

        $adminPermissions = $administrador->permissions->pluck('name')->all();
        $ingenieroPermissions = $ingeniero->permissions->pluck('name')->all();

        $this->assertNotEmpty($ingenieroPermissions);
        foreach ($ingenieroPermissions as $permission) {
            $this->assertContains($permission, $adminPermissions);
        }

        $this->assertLessThan(count($adminPermissions), count($ingenieroPermissions), 'ingeniero nunca debe ser un superset (ni igual) de administrador');
    }
}
