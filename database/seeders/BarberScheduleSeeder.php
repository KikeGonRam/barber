<?php

namespace Database\Seeders;

use App\Models\Barber;
use Illuminate\Database\Seeder;

/**
 * Siembra ÚNICAMENTE la colección `barber_schedules` — el horario semanal
 * estándar (Lun–Sáb 09:00–21:00, Domingo descanso) para cada Barber ya
 * creado por BarberSeeder.
 *
 * Ejecutar DESPUÉS de BarberSeeder.
 */
class BarberScheduleSeeder extends Seeder
{
    // day_of_week: 1=Lun … 6=Sáb, 0=Dom (descanso)
    private const WORK_DAYS = [1, 2, 3, 4, 5, 6];

    public function run(): void
    {
        $barbers = Barber::all();

        if ($barbers->isEmpty()) {
            $this->command->error('BarberScheduleSeeder: no hay barberos. Ejecuta BarberSeeder primero.');
            return;
        }

        foreach ($barbers as $barber) {
            self::seedFor($barber);
        }

        $this->command->info("  ✓ Horario semanal creado para {$barbers->count()} barberos");
    }

    /**
     * Horario semanal estándar (Lun–Sáb 09:00–21:00, Domingo descanso) para
     * un solo Barber. Método reutilizable para otros seeders que necesiten
     * crear un barbero con horario completo sin duplicar esta lógica.
     */
    public static function seedFor(Barber $barber): void
    {
        foreach (self::WORK_DAYS as $day) {
            $barber->schedules()->updateOrCreate(
                ['day_of_week' => $day],
                ['start_time' => '09:00:00', 'end_time' => '21:00:00', 'is_working' => true]
            );
        }

        // Domingo — descanso
        $barber->schedules()->updateOrCreate(
            ['day_of_week' => 0],
            ['start_time' => null, 'end_time' => null, 'is_working' => false]
        );
    }
}
