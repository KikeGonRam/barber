<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Solo lo mínimo para arrancar una base nueva: roles/permisos y el administrador inicial. Los seeders de
     * demostración (citas, pagos, clientes, catálogo, portafolio…) se eliminaron el 2026-10-09: sembraron los
     * ~200k registros falsos del incidente de septiembre y el portafolio fantasma de producción. El catálogo real
     * (servicios, productos) se carga desde la aplicación, y la configuración de la barbería se crea sola.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
