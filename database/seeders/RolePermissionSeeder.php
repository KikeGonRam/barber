<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.ver',
            'usuarios.gestionar',
            'barberos.gestionar',
            'clientes.gestionar',
            'servicios.gestionar',
            'citas.gestionar',
            'citas.ver_propias',
            'pagos.gestionar',
            'inventario.ver',
            'inventario.gestionar',
            'reportes.ver',
            'configuracion.gestionar',
            'logs.ver',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'administrador' => $permissions,
            'barbero' => [
                'dashboard.ver',
                'citas.ver_propias',
            ],
            'recepcionista' => [
                'dashboard.ver',
                'clientes.gestionar',
                'citas.gestionar',
                'pagos.gestionar',
                'inventario.ver',
            ],
            'cliente' => [
                'dashboard.ver',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
        }
    }
}
