<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Seeder;

class WorkSeeder extends Seeder
{
    public function run(): void
    {
        $barbers = Barber::with('user')->where('activo', true)->get();

        if ($barbers->isEmpty()) {
            $this->command->warn('WorkSeeder: no hay barberos activos. Ejecuta DemoDataSeeder primero.');
            return;
        }

        $titles = [
            'Fade clásico', 'Degradado suave', 'Barba estilo', 'Corte skin fade',
            'Desvanecido con textura', 'Corte clásico caballero', 'Estilo moderno',
            'Pompadour', 'Undercut', 'Buzz cut con línea', 'Barba completa arreglada',
        ];

        $descriptions = [
            'Trabajo realizado con tijeras y máquina, perfecto para ocasiones formales.',
            'Degradado natural de bajo a alto, textura mate con cera.',
            'Barba moldeada con cera, líneas definidas y humectada.',
            'Skin fade con detalle de textura en la parte superior.',
            'Corte suave que resalta las facciones del cliente.',
        ];

        foreach ($barbers as $barber) {
            $numWorks = rand(3, 6);

            for ($i = 0; $i < $numWorks; $i++) {
                Work::create([
                    'barbero_id'  => (string) $barber->user_id,
                    'title'       => fake()->randomElement($titles),
                    'description' => fake()->randomElement($descriptions),
                    'work_date'   => now()->subDays(rand(1, 60))->toDateString(),
                ]);
            }
        }

        $this->command->info('WorkSeeder: portfolio creado para '.count($barbers).' barberos.');
    }
}
