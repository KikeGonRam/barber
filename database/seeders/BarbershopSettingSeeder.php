<?php

namespace Database\Seeders;

use App\Models\BarbershopSetting;
use Illuminate\Database\Seeder;

class BarbershopSettingSeeder extends Seeder
{
    public function run(): void
    {
        BarbershopSetting::updateOrCreate([], [
            'nombre' => 'UrbanBlade',
            'direccion' => 'Av. Insurgentes Sur 1234, Col. Del Valle, Ciudad de Mexico',
            'telefono' => '5512345678',
            'horario_apertura' => '09:00',
            'horario_cierre' => '21:00',
            'politica_cancelacion' => 24,
            'redes_sociales' => [
                'instagram' => 'https://instagram.com/urbanblade',
                'facebook' => 'https://facebook.com/urbanblade',
                'tiktok' => 'https://tiktok.com/@urbanblade',
            ],
            'datos_bancarios' => [
                'banco' => 'BBVA',
                'clabe' => '012180001234567895',
                'titular' => 'UrbanBlade Grooming Studio SA de CV',
            ],
            'maintenance_mode' => false,
        ]);

        $this->command->info('Configuracion de la barberia sembrada.');
    }
}
