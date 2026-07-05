<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        BarbershopSetting::updateOrCreate(
            ['id' => 1],
            [
                'nombre' => 'BarberPro Elite',
                'horario_apertura' => '09:00',
                'horario_cierre' => '21:00',
                'politica_cancelacion' => 24,
                'maintenance_mode' => false,
            ]
        );

        $seedSchedule = static function (Barber $barber): void {
            for ($day = 1; $day <= 6; $day++) {
                $barber->schedules()->updateOrCreate(
                    ['day_of_week' => $day],
                    [
                        'start_time' => '09:00:00',
                        'end_time' => '21:00:00',
                        'is_working' => true,
                    ]
                );
            }

            $barber->schedules()->updateOrCreate(
                ['day_of_week' => 0],
                [
                    'start_time' => null,
                    'end_time' => null,
                    'is_working' => false,
                ]
            );
        };

        // Recepcionista principal
        $recep = User::updateOrCreate(
            ['email' => 'recepcionista@test.com'],
            [
                'name' => 'Recepcionista Test',
                'password' => 'Recep@Urban2025!',
                'email_verified_at' => now(),
            ]
        );
        $recep->assignRole('recepcionista');

        // Barbero principal (perfil completo)
        $barber = User::updateOrCreate(
            ['email' => 'barbero@test.com'],
            [
                'name' => 'Barbero Test',
                'password' => 'Barber@Urban2025!',
                'email_verified_at' => now(),
            ]
        );
        $barber->assignRole('barbero');
        $barberProfile = Barber::firstOrCreate([
            'user_id' => $barber->id,
        ], [
            'especialidades' => 'Fade, Barba',
            'foto' => null,
            'descripcion' => 'Barbero de pruebas automatizadas',
            'activo' => true,
        ]);
        $seedSchedule($barberProfile);

        // Cliente principal (perfil completo)
        $client = User::updateOrCreate(
            ['email' => 'cliente@test.com'],
            [
                'name' => 'Cliente Test',
                'password' => 'Cliente@Urban2025!',
                'email_verified_at' => now(),
            ]
        );
        $client->assignRole('cliente');
        Client::firstOrCreate([
            'user_id' => $client->id,
        ], [
            'telefono' => '+521234567890',
            'fecha_nacimiento' => '1990-01-01',
            'preferencias_notificacion' => [
                'in_app' => true,
                'email' => true,
                'sms' => false,
                'whatsapp' => true,
            ],
        ]);

        // Usuarios extra para pruebas CRUD
        foreach ([1, 2] as $i) {
            // Recepcionista extra
            $r = User::updateOrCreate(
                ['email' => "recepcionista${i}@test.com"],
                [
                    'name' => "Recepcionista Test ${i}",
                    'password' => 'Recep@Urban2025!',
                    'email_verified_at' => now(),
                ]
            );
            $r->assignRole('recepcionista');

            // Barbero extra (perfil completo)
            $b = User::updateOrCreate(
                ['email' => "barbero${i}@test.com"],
                [
                    'name' => "Barbero Test ${i}",
                    'password' => 'Barber@Urban2025!',
                    'email_verified_at' => now(),
                ]
            );
            $b->assignRole('barbero');
            $barberExtra = Barber::firstOrCreate([
                'user_id' => $b->id,
            ], [
                'especialidades' => 'Corte clásico',
                'foto' => null,
                'descripcion' => "Barbero de pruebas ${i}",
                'activo' => true,
            ]);
            $seedSchedule($barberExtra);

            // Cliente extra (perfil completo)
            $c = User::updateOrCreate(
                ['email' => "cliente${i}@test.com"],
                [
                    'name' => "Cliente Test ${i}",
                    'password' => 'Cliente@Urban2025!',
                    'email_verified_at' => now(),
                ]
            );
            $c->assignRole('cliente');
            Client::firstOrCreate([
                'user_id' => $c->id,
            ], [
                'telefono' => '+52123456789'.$i,
                'fecha_nacimiento' => '199'.$i.'-02-01',
                'preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => ($i % 2 === 0),
                ],
            ]);
        }
    }
}
