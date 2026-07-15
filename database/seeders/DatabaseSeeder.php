<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden de siembra: cada seeder cubre una sola coleccion y depende
     * unicamente de lo sembrado por los anteriores en esta lista.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            BarbershopSettingSeeder::class,
            ServiceSeeder::class,
            ProductSeeder::class,
            AdminUserSeeder::class,
            ReceptionUserSeeder::class,
            BarberSeeder::class,
            BarberScheduleSeeder::class,
            ClientSeeder::class,
            AppointmentSeeder::class,
            PaymentSeeder::class,
            LoyaltyTransactionSeeder::class,
            OrderSeeder::class,
            WorkSeeder::class,
            WorkImageSeeder::class,
            CommentSeeder::class,
            ReactionSeeder::class,
        ]);
    }
}
