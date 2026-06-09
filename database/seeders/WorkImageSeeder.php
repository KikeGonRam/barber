<?php

namespace Database\Seeders;

use App\Models\Work;
use Illuminate\Database\Seeder;

class WorkImageSeeder extends Seeder
{
    public function run(): void
    {
        $works = Work::all();

        if ($works->isEmpty()) {
            $this->command->warn('WorkImageSeeder: no hay works. Ejecuta WorkSeeder primero.');
            return;
        }

        $placeholders = [
            'https://placehold.co/800x600/1a1a1a/d4af37?text=Corte+1',
            'https://placehold.co/800x600/1a1a1a/d4af37?text=Corte+2',
            'https://placehold.co/800x600/1a1a1a/d4af37?text=Barba+1',
            'https://placehold.co/800x600/1a1a1a/d4af37?text=Fade+1',
            'https://placehold.co/800x600/1a1a1a/d4af37?text=Estilo+1',
        ];

        foreach ($works as $work) {
            $numImages = rand(1, 3);
            for ($i = 0; $i < $numImages; $i++) {
                $work->images()->create([
                    'image' => fake()->randomElement($placeholders),
                ]);
            }
        }

        $this->command->info('WorkImageSeeder: imágenes creadas para '.count($works).' works.');
    }
}
