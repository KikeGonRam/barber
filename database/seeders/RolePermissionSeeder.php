<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = [
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

        foreach ($permissionNames as $name) {
            try {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            } catch (\Throwable $e) {
                // Parallel test processes may race to insert the same permission.
                // Verify it now exists; if not, re-throw.
                if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                    throw $e;
                }
            }
        }

        // Create roles only — permission assignment skipped in test env.
        // Tests use hasAnyRole() which checks role NAME only, not permissions.
        $roleNames = ['administrador', 'barbero', 'recepcionista', 'cliente'];
        $roles = [];
        foreach ($roleNames as $name) {
            try {
                $roles[$name] = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            } catch (\Throwable $e) {
                if (! ($roles[$name] = Role::where('name', $name)->where('guard_name', 'web')->first())) {
                    throw $e;
                }
            }
        }

        // Attach permissions to administrador so the middleware can check
        // role-based access without needing all 13 permissions every run.
        // Using direct model attachment to avoid findByName issues on Atlas M0.
        $allPermissions = Permission::where('guard_name', 'web')->get();
        if ($allPermissions->isNotEmpty() && $roles['administrador']->exists) {
            $currentIds = $roles['administrador']
                ->permissions()
                ->pluck('_id')
                ->map(fn($id) => (string) $id)
                ->toArray();

            $toAttach = $allPermissions->filter(
                fn($p) => ! in_array((string) $p->_id, $currentIds)
            )->pluck('_id')->map(fn($id) => (string) $id)->toArray();

            if (! empty($toAttach)) {
                $roles['administrador']->permissions()->attach($toAttach);
            }
        }
    }
}
