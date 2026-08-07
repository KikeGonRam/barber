<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Comando manual de diagnóstico (no está en el scheduler) que compara los
 * permisos reales de un usuario de ejemplo por cada rol contra la matriz
 * esperada definida en ROLE_EXPECTATIONS, para detectar configuraciones de
 * roles/permisos rotas. Se ejecuta a mano tras cambios en roles o seeders.
 */
class ValidateUserRoles extends Command
{
    protected $signature = 'validate:user-roles';

    protected $description = 'Valida que los roles y permisos reales del sistema estén configurados correctamente';

    // Matriz de permisos esperados por rol: sirve como "fuente de verdad" contra
    // la que se comparan los permisos reales asignados en la base de datos.
    private const ROLE_EXPECTATIONS = [
        'administrador' => [
            'citas.gestionar' => true,
            'pagos.gestionar' => true,
            'inventario.ver' => true,
            'inventario.gestionar' => true,
            'clientes.gestionar' => true,
            'reportes.ver' => true,
            'servicios.gestionar' => true,
            'usuarios.gestionar' => true,
            'barberos.gestionar' => true,
            'configuracion.gestionar' => true,
            'logs.ver' => true,
        ],
        'recepcionista' => [
            'citas.gestionar' => true,
            'pagos.gestionar' => true,
            'inventario.ver' => true,
            'inventario.gestionar' => true,
            'clientes.gestionar' => true,
            'reportes.ver' => false,
            'servicios.gestionar' => false,
            'usuarios.gestionar' => false,
            'barberos.gestionar' => false,
            'configuracion.gestionar' => false,
            'logs.ver' => false,
        ],
        'barbero' => [
            'citas.gestionar' => false,
            'pagos.gestionar' => false,
            'inventario.ver' => false,
            'inventario.gestionar' => false,
            'clientes.gestionar' => false,
            'reportes.ver' => false,
            'servicios.gestionar' => false,
            'usuarios.gestionar' => false,
            'barberos.gestionar' => false,
            'configuracion.gestionar' => false,
            'logs.ver' => false,
        ],
        'cliente' => [
            'citas.gestionar' => false,
            'pagos.gestionar' => false,
            'inventario.ver' => false,
            'inventario.gestionar' => false,
            'clientes.gestionar' => false,
            'reportes.ver' => false,
            'servicios.gestionar' => false,
            'usuarios.gestionar' => false,
            'barberos.gestionar' => false,
            'configuracion.gestionar' => false,
            'logs.ver' => false,
        ],
    ];

    /**
     * Para cada rol definido en ROLE_EXPECTATIONS, toma un usuario de ejemplo
     * y verifica que sus permisos (directos y vía middleware) coincidan con
     * lo esperado, marcando el comando como fallido si hay discrepancias.
     */
    public function handle(): int
    {
        $this->info('Validando roles y permisos...');

        $userCount = User::query()->count();

        if ($userCount === 0) {
            $this->error('No hay usuarios en la base de datos. Ejecuta los seeders primero.');

            return self::FAILURE;
        }

        $this->line('Usuarios encontrados: '.$userCount);

        foreach (array_keys(self::ROLE_EXPECTATIONS) as $roleName) {
            $this->line("- {$roleName}: ".User::query()->whereRoleName($roleName)->count());
        }

        $allPass = true;

        foreach (self::ROLE_EXPECTATIONS as $roleName => $expectations) {
            $user = User::query()->whereRoleName($roleName)->first();

            if (! $user) {
                $this->warn("No hay usuario de ejemplo para el rol {$roleName}; se omite validación puntual.");

                continue;
            }

            $this->line('');
            $this->info(ucfirst($roleName));

            foreach ($expectations as $permission => $shouldHave) {
                // Se comprueban dos vias de verificacion (directa y la que usaria el
                // middleware de rutas) para detectar si divergen entre si.
                $hasPermission = $user->checkPermissionTo($permission);
                $hasPermissionThroughMiddleware = $user->hasAnyPermission([$permission]);
                $status = $hasPermission === $shouldHave && $hasPermissionThroughMiddleware === $shouldHave
                    ? 'OK'
                    : 'FAIL';

                $this->line(sprintf(
                    '  [%s] %-26s esperado=%s directo=%s middleware=%s',
                    $status,
                    $permission,
                    $shouldHave ? 'si' : 'no',
                    $hasPermission ? 'si' : 'no',
                    $hasPermissionThroughMiddleware ? 'si' : 'no',
                ));

                if ($hasPermission !== $shouldHave || $hasPermissionThroughMiddleware !== $shouldHave) {
                    $allPass = false;
                }
            }
        }

        if (! $allPass) {
            $this->error('La matriz de permisos no coincide con la configuración esperada.');

            return self::FAILURE;
        }

        $this->info('Validación de roles completada correctamente.');

        return self::SUCCESS;
    }
}
