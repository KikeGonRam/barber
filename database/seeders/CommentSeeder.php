<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    private const COMMENTS = [
        'Excelente trabajo, se nota la dedicacion.', 'Quedo increible, felicidades!',
        'El mejor barbero de la zona, sin duda.', 'Que precision en el degradado.',
        'Ya quiero mi cita para que me dejen asi.', 'Un artista con las tijeras.',
        'Se ve buenisimo, recomendado al 100%.', 'Justo el estilo que buscaba.',
        'Trabajo limpio y profesional.', 'Vengo desde hace años y nunca falla.',
        'Que nivel de detalle, impresionante.', 'Definitivamente mi barberia de confianza.',
    ];

    public function run(): void
    {
        $allClientIds = User::whereRoleName('cliente')->pluck('id')->map(fn ($id) => (string) $id)->all();
        $userIds = collect($allClientIds)->random(min(600, count($allClientIds)))->values()->all();

        if (empty($userIds)) {
            $this->command->warn('No hay clientes; se omite CommentSeeder.');

            return;
        }

        $rows = [];
        $total = 0;
        $now = now();

        Work::query()->select(['_id'])->cursor()->each(function (Work $work) use (&$rows, &$total, $userIds, $now) {
            if (random_int(1, 100) > 60) {
                return;
            }

            $count = random_int(1, 4);
            for ($i = 0; $i < $count; $i++) {
                $rows[] = [
                    'work_id' => (string) $work->id,
                    'user_id' => $userIds[array_rand($userIds)],
                    'comment' => self::COMMENTS[array_rand(self::COMMENTS)],
                    'rating' => random_int(4, 5),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;
            }

            if (count($rows) >= 1000) {
                Comment::insert($rows);
                $rows = [];
            }
        });

        if (! empty($rows)) {
            Comment::insert($rows);
        }

        $this->command->info("Comentarios sembrados: {$total}");
    }
}
