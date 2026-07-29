<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ValidateUserRoles extends Command
{
    protected $signature = 'validate:user-roles';

    protected $description = 'Valida que los roles y permisos reales del sistema estén configurados correctamente';

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
