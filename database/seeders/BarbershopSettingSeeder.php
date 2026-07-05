<?php

namespace Database\Seeders;

use App\Models\BarbershopSetting;
use Illuminate\Database\Seeder;

/**
 * Siembra la configuración inicial de UrbanBlade.
 * Editar los valores antes de correr en producción real.
 */
class BarbershopSettingSeeder extends Seeder
{
    public function run(): void
    {
        BarbershopSetting::updateOrCreate(
            [],   // siempre existe solo 1 registro de configuración
            [
                'nombre'               => 'UrbanBlade',
                'direccion'            => env('BARBERSHOP_DIRECCION', 'Av. Principal 123, Ciudad de México'),
                'telefono'             => env('BARBERSHOP_TELEFONO', '+52 55 0000 0000'),
                'horario_apertura'     => '09:00',
                'horario_cierre'       => '21:00',
                'politica_cancelacion' => 24,    // horas mínimas para cancelar sin cargo
                'maintenance_mode'     => false,
                'redes_sociales'       => [
                    'instagram' => env('BARBERSHOP_INSTAGRAM', '@urbanblade'),
                    'facebook'  => env('BARBERSHOP_FACEBOOK', ''),
                    'tiktok'    => env('BARBERSHOP_TIKTOK', '@urbanblade'),
                ],
            ]
        );

        $this->command->info('  ✓ Configuración de UrbanBlade guardada');
    }
}
