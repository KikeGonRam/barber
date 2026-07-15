<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\BarberSchedule;
use Illuminate\Database\Seeder;

class BarberScheduleSeeder extends Seeder
{
    /**
     * Horario por defecto: Lunes a Sabado 9:00-21:00, Domingo descanso.
     * Mismo patron que BarberDashboardController::schedule() genera bajo demanda.
     */
    public function run(): void
    {
        $rows = [];
        $now = now();

        Barber::query()->select(['_id'])->cursor()->each(function (Barber $barber) use (&$rows, $now) {
            for ($day = 0; $day <= 6; $day++) {
                $rows[] = [
                    'barber_id' => (string) $barber->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '21:00:00',
                    'is_working' => $day !== 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        });

        collect($rows)->chunk(500)->each(fn ($chunk) => BarberSchedule::insert($chunk->all()));

        $this->command->info('Horarios sembrados: '.count($rows).' (7 por barbero)');
    }
}
