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
        // Rol de solo lectura para alguien de sistemas: ve estado del
        // servidor (Pulse), analitica y reportes de cada modulo, pero no
        // puede gestionar nada de negocio (crear/editar/eliminar usuarios,
        // clientes, servicios, barberos, citas, pagos, inventario ni
        // configuracion). Deliberadamente NO es un superset de
        // administrador -- decision explicita del dueno del proyecto
        // (2026-09-06) para no ampliar la superficie de riesgo si esta
        // cuenta se compromete. Ver .claude/skills/urbanblade-guardrails
        // guardrail #24 antes de agregar 'ingeniero' a cualquier ruta.
        'ingeniero' => [
            'reportes.ver',
            'logs.ver',
            'sistema.ver',
        ],
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
