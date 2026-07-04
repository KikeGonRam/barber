<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,    // 1 — roles y permisos (prerequisito de todo)
            AdminUserSeeder::class,          // 2 — administrador principal
            BarbershopSettingSeeder::class,  // 3 — configuración de la barbería
            ServiceSeeder::class,            // 4 — catálogo de 20 servicios reales
            ProductSeeder::class,            // 5 — 30 productos de barbería (15 venta + 15 insumo)
            ProductionSeeder::class,         // 6 — recepcionista + 25 barberos + 1000 clientes
            HistoricalDataSeeder::class,     // 7 — +10,000 citas/pagos/puntos históricos
            WorkSeeder::class,               // 8 — trabajos del portafolio
            WorkImageSeeder::class,          // 9 — imágenes reales del portafolio
            CommentSeeder::class,            // 10 — comentarios de clientes en el portafolio
            ReactionSeeder::class,           // 11 — likes de clientes en el portafolio
        ]);
    }
}
