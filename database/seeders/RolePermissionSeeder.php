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
            // Dashboard
            'dashboard.ver',
            // Usuarios
            'usuarios.gestionar',
            'usuarios.ver',
            // Barberos
            'barberos.gestionar',
            'barberos.ver',
            // Clientes
            'clientes.gestionar',
            'clientes.ver',
            // Servicios
            'servicios.gestionar',
            'servicios.ver',
            // Citas
            'citas.gestionar',
            'citas.ver',
            'citas.ver_propias',
            'citas.crear',
            'citas.cancelar',
            // Pagos
            'pagos.gestionar',
            'pagos.ver',
            'pagos.stripe',
            // Inventario
            'inventario.ver',
            'inventario.gestionar',
            // Reportes
            'reportes.ver',
            'reportes.gestionar',
            // Configuración
            'configuracion.gestionar',
            // Logs
            'logs.ver',
            // Portfolio
            'portfolio.gestionar',
            'portfolio.ver',
            // Notificaciones
            'notificaciones.ver',
            'notificaciones.gestionar',
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

        $allPermissions = Permission::where('guard_name', 'web')->get();
        $byName = $allPermissions->keyBy('name');

        $rolePermissions = [
            'administrador' => $allPermissions->pluck('_id')->map(fn($id) => (string) $id)->toArray(),

            'recepcionista' => collect([
                'dashboard.ver',
                'clientes.gestionar', 'clientes.ver',
                'citas.gestionar', 'citas.ver', 'citas.crear', 'citas.cancelar',
                'pagos.gestionar', 'pagos.ver', 'pagos.stripe',
                'barberos.ver',
                'servicios.ver',
                'inventario.ver',
                'reportes.ver',
                'notificaciones.ver',
            ])->map(fn($n) => isset($byName[$n]) ? (string) $byName[$n]->_id : null)->filter()->values()->toArray(),

            'barbero' => collect([
                'dashboard.ver',
                'citas.ver_propias', 'citas.ver',
                'portfolio.gestionar', 'portfolio.ver',
                'clientes.ver',
                'servicios.ver',
                'reportes.ver',
                'notificaciones.ver',
            ])->map(fn($n) => isset($byName[$n]) ? (string) $byName[$n]->_id : null)->filter()->values()->toArray(),

            'cliente' => collect([
                'citas.crear', 'citas.ver_propias', 'citas.cancelar',
                'barberos.ver',
                'servicios.ver',
                'portfolio.ver',
                'notificaciones.ver',
            ])->map(fn($n) => isset($byName[$n]) ? (string) $byName[$n]->_id : null)->filter()->values()->toArray(),
        ];

        foreach ($rolePermissions as $roleName => $permIds) {
            if (empty($permIds) || ! isset($roles[$roleName])) {
                continue;
            }
            $currentIds = $roles[$roleName]
                ->permissions()
                ->pluck('_id')
                ->map(fn($id) => (string) $id)
                ->toArray();

            $toAttach = array_values(array_diff($permIds, $currentIds));

            if (! empty($toAttach)) {
                $roles[$roleName]->permissions()->attach($toAttach);
            }
        }
    }
}
