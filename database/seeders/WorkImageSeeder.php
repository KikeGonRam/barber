<?php

namespace Database\Seeders;

use App\Models\Work;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class WorkImageSeeder extends Seeder
{
    private const TARGET_IMAGES_PER_WORK = 2;

    public function run(): void
    {
        $works = Work::all();

        if ($works->isEmpty()) {
            $this->command->warn('WorkImageSeeder: no hay works. Ejecuta WorkSeeder primero.');
            return;
        }

        $imagePool = collect(Storage::disk('public')->files('portfolio'))
            ->filter(static function (string $path): bool {
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
            })
            ->values();

        if ($imagePool->isEmpty()) {
            $this->command->warn('WorkImageSeeder: no hay imágenes locales en storage/app/public/portfolio.');
            return;
        }

        $createdImages = 0;

        foreach ($works as $work) {
            $existingImages = $work->images()->count();
            $neededImages = self::TARGET_IMAGES_PER_WORK - $existingImages;

            if ($neededImages <= 0) {
                continue;
            }

            for ($i = 0; $i < $neededImages; $i++) {
                $index = crc32((string) $work->id.'|'.$i) % $imagePool->count();
                $imagePath = $imagePool[$index];

                $work->images()->create([
                    'image' => $imagePath,
                    'type' => 'image',
                    'mime_type' => Storage::disk('public')->mimeType($imagePath) ?: 'image/jpeg',
                ]);

                $createdImages++;
            }
        }

        $this->command->info('WorkImageSeeder: '.$createdImages.' imágenes locales asignadas a '.count($works).' works.');
    }
}
