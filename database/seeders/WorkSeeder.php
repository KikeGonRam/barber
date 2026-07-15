<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    private const TITLES = [
        'Fade limpio de la semana', 'Transformacion total', 'Barba a navaja perfecta',
        'Corte clasico con estilo', 'Degradado quirurgico', 'Antes y despues',
        'Look renovado para el finde', 'Diseno personalizado', 'Precision en cada linea',
        'Combo completo listo', 'Estilo moderno urbano', 'Trabajo de detalle',
    ];

    private const DESCRIPTIONS = [
        'Otro cliente satisfecho saliendo con estilo de UrbanBlade.',
        'Trabajo minucioso, resultado impecable. Agenda tu cita.',
        'Cada corte cuenta una historia. Este es el resultado de hoy.',
        'Precision y estilo en cada visita. Vengan a conocernos.',
        'Transformando looks, un cliente a la vez.',
        'La combinacion perfecta de tecnica clasica y tendencia actual.',
        null,
    ];

    public function run(): void
    {
        $barberUserIds = User::whereRoleName('barbero')->pluck('id')->map(fn ($id) => (string) $id)->all();

        if (empty($barberUserIds)) {
            $this->command->warn('No hay barberos; se omite WorkSeeder.');

            return;
        }

        $total = 0;

        foreach ($barberUserIds as $userId) {
            $count = random_int(2, 6);

            for ($i = 0; $i < $count; $i++) {
                Work::create([
                    'barbero_id' => $userId,
                    'title' => self::TITLES[array_rand(self::TITLES)],
                    'description' => self::DESCRIPTIONS[array_rand(self::DESCRIPTIONS)],
                    'work_date' => Carbon::now()->subDays(random_int(0, 540)),
                ]);
                $total++;
            }
        }

        $this->command->info("Publicaciones sembradas: {$total}");
    }
}
