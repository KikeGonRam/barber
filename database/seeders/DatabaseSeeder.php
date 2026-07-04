<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Cada seeder siembra UNA sola colección (o un par estrechamente
        // acoplado, como roles+permisos). El orden importa: cada uno depende
        // de que el/los anterior(es) ya hayan creado sus datos.
        $this->call([
            RolePermissionSeeder::class,      // 1  — roles, permissions
            AdminUserSeeder::class,           // 2  — users (administrador)
            BarbershopSettingSeeder::class,   // 3  — barbershop_settings
            ServiceSeeder::class,             // 4  — services (20 reales)
            ProductSeeder::class,             // 5  — products (15 venta + 15 insumo)
            UserSeeder::class,                // 6  — users (recepcionista + 25 barbero + 1000 cliente)
            BarberSeeder::class,              // 7  — barbers (perfil profesional)
            BarberScheduleSeeder::class,      // 8  — barber_schedules (horario semanal)
            ClientSeeder::class,              // 9  — clients (perfil de cliente)
            AppointmentSeeder::class,         // 10 — appointments (~12,500 citas históricas)
            PaymentSeeder::class,             // 11 — payments (1 por cita completada)
            LoyaltyTransactionSeeder::class,  // 12 — loyalty_transactions (+ stats de clients)
            WorkSeeder::class,                // 13 — works (portafolio)
            WorkImageSeeder::class,           // 14 — work_images
            CommentSeeder::class,             // 15 — comments (portafolio)
            ReactionSeeder::class,            // 16 — reactions (portafolio)
        ]);
    }
}
