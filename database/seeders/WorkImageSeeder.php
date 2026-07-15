<?php

namespace Database\Seeders;

use App\Models\Work;
use App\Models\WorkImage;
use Illuminate\Database\Seeder;

class WorkImageSeeder extends Seeder
{
    /** Fotos reales de cortes/barba en Unsplash, curadas para el portafolio. */
    private const PHOTO_IDS = [
        'photo-1599351431202-1e0f0137899a', 'photo-1503951914875-452162b0f3f1',
        'photo-1534297635766-a262cdcb8ee4', 'photo-1605497787928-40e1c74e4e74',
        'photo-1622286342621-4bd786c2447c', 'photo-1583863788434-e58a36330cf0',
        'photo-1621605815971-fbc98d665033', 'photo-1617450365226-9bf28c04e130',
        'photo-1585747860715-2ba37e788b70', 'photo-1580618672591-eb180b1a973f',
        'photo-1519345182560-3f2917c472ef', 'photo-1560250097-0b93528c311a',
        'photo-1507003211169-0a1dd7228f2d', 'photo-1592647420148-bfcc177e2117',
        'photo-1605497788044-5a32c7078486',
    ];

    public function run(): void
    {
        $rows = [];
        $total = 0;
        $now = now();

        Work::query()->select(['_id'])->cursor()->each(function (Work $work) use (&$rows, &$total, $now) {
            $count = random_int(1, 3);

            for ($i = 0; $i < $count; $i++) {
                $photoId = self::PHOTO_IDS[array_rand(self::PHOTO_IDS)];

                $rows[] = [
                    'work_id' => (string) $work->id,
                    'image' => "https://images.unsplash.com/{$photoId}?w=900&h=1100&auto=format&fit=crop&q=85",
                    'type' => 'image',
                    'mime_type' => 'image/jpeg',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;
            }

            if (count($rows) >= 1000) {
                WorkImage::insert($rows);
                $rows = [];
            }
        });

        if (! empty($rows)) {
            WorkImage::insert($rows);
        }

        $this->command->info("Imagenes de publicaciones sembradas: {$total}");
    }
}
