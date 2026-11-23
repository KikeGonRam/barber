<?php

namespace Tests\UT;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * El rol "ingeniero" existió desde el 2026-09-06 para alguien de sistemas que necesitaba ver
 * estado del servidor/analítica/reportes sin poder gestionar nada de negocio. El dueño del
 * proyecto decidió quitarlo el 23-nov-2026 tras la auditoría de funciones (nadie del equipo lo
 * usaba): RolePermissionSeeder ya no lo siembra ni le sincroniza permisos (ver su propio
 * docblock). Este test cubre la lista de roles vigente y que ninguno de ellos tenga un permiso
 * *.gestionar fuera de lo esperado.
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

    public function test_seeds_exactly_the_current_set_of_roles_without_ingeniero(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $roleNames = Role::where('guard_name', 'web')->pluck('name')->sort()->values()->all();

        $this->assertSame(['administrador', 'barbero', 'cliente', 'recepcionista'], $roleNames);
        $this->assertNotContains('ingeniero', $roleNames, 'el rol ingeniero se quitó el 23-nov-2026 y ya no debe sembrarse');
    }

    public function test_administrador_is_the_only_role_with_management_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $administrador = Role::where('name', 'administrador')->where('guard_name', 'web')->firstOrFail();
        $adminPermissions = $administrador->permissions->pluck('name')->all();

        $managementPermissions = array_filter($adminPermissions, fn (string $permission) => str_ends_with($permission, '.gestionar'));
        $this->assertNotEmpty($managementPermissions, 'administrador debe conservar los permisos de gestión de negocio');

        foreach (['barbero', 'cliente'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
            $this->assertSame([], $role->permissions->pluck('name')->all(), "{$roleName} no debe tener ningún permiso sincronizado por el seeder");
        }

        $recepcionista = Role::where('name', 'recepcionista')->where('guard_name', 'web')->firstOrFail();
        $recepcionistaPermissions = $recepcionista->permissions->pluck('name')->all();
        $this->assertNotContains('usuarios.gestionar', $recepcionistaPermissions);
        $this->assertNotContains('configuracion.gestionar', $recepcionistaPermissions);
    }
}
