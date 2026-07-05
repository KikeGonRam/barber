<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\Work;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    private const TARGET_WORKS_PER_BARBER = 10;

    public function run(): void
    {
        $barbers = Barber::with('user')->where('activo', true)->get();

        if ($barbers->isEmpty()) {
            $this->command->warn('WorkSeeder: no hay barberos activos. Ejecuta BarberSeeder primero.');
            return;
        }

        $titles = [
            'Fade clásico', 'Degradado suave', 'Barba estilo', 'Corte skin fade',
            'Desvanecido con textura', 'Corte clásico caballero', 'Estilo moderno',
            'Pompadour', 'Undercut', 'Buzz cut con línea', 'Barba completa arreglada',
            'Taper limpio', 'French crop texturizado', 'Burst fade', 'Peinado ejecutivo',
        ];

        $descriptions = [
            'Trabajo realizado con tijeras y máquina, perfecto para ocasiones formales.',
            'Degradado natural de bajo a alto, textura mate con cera.',
            'Barba moldeada con cera, líneas definidas y humectada.',
            'Skin fade con detalle de textura en la parte superior.',
            'Corte suave que resalta las facciones del cliente.',
            'Acabado limpio con contornos nítidos y transición suave.',
            'Look moderno con textura controlada y perfilado detallado.',
        ];

        $createdWorks = 0;

        foreach ($barbers as $barber) {
            $existingWorks = Work::where('barbero_id', (string) $barber->user_id)->count();
            $numWorks = max(0, self::TARGET_WORKS_PER_BARBER - $existingWorks);

            for ($i = 0; $i < $numWorks; $i++) {
                Work::create([
                    'barbero_id'  => (string) $barber->user_id,
                    'title'       => fake()->randomElement($titles).' #'.($existingWorks + $i + 1),
                    'description' => fake()->randomElement($descriptions),
                    'work_date'   => now()->subDays(rand(1, 90))->toDateString(),
                ]);

                $createdWorks++;
            }
        }

        $this->command->info('WorkSeeder: '.$createdWorks.' trabajos nuevos creados para '.count($barbers).' barberos.');
    }
}
