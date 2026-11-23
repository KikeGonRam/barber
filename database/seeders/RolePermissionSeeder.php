<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permisos reales exigidos por permission.custom: en routes/web.php.
     */
    private const PERMISSIONS = [
        'citas.gestionar',
        'pagos.gestionar',
        'inventario.ver',
        'inventario.gestionar',
        'clientes.gestionar',
        'reportes.ver',
        'servicios.gestionar',
        'usuarios.gestionar',
        'barberos.gestionar',
        'configuracion.gestionar',
        'logs.ver',
        'sistema.ver',
    ];

    private const ROLE_PERMISSIONS = [
        'administrador' => self::PERMISSIONS,
        'recepcionista' => [
            'citas.gestionar',
            'pagos.gestionar',
            'clientes.gestionar',
            'inventario.ver',
            'inventario.gestionar',
        ],
        // El rol de solo lectura 'ingeniero' (estado del servidor, analítica y reportes, sin gestionar nada
        // de negocio) existió desde el 2026-09-06 hasta el 23-nov-2026, cuando el dueño del proyecto decidió
        // quitarlo tras la auditoría de funciones: nadie del equipo lo usaba. Este seeder ya no lo crea ni le
        // sincroniza permisos; un Role o User que ya lo tuviera en la base de datos no se borra desde aquí
        // (los seeders no tocan datos existentes -- ver CLAUDE.md), pero ninguna ruta ni Gate de la API lo
        // reconoce ya (ver routes/api.php y AppServiceProvider::boot()), así que queda sin ningún permiso
        // efectivo.
        'barbero' => [],
        'cliente' => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if (! empty($permissions)) {
                $role->syncPermissions($permissions);
            }
        }

        $this->command->info('Roles y permisos sembrados: '.implode(', ', array_keys(self::ROLE_PERMISSIONS)));
    }
}
