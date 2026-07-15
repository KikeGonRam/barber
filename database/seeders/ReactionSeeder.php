<?php

namespace Database\Seeders;

use App\Models\Reaction;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Seeder;

class ReactionSeeder extends Seeder
{
    public function run(): void
    {
        $allClientIds = User::whereRoleName('cliente')->pluck('id')->map(fn ($id) => (string) $id)->all();
        $userIds = collect($allClientIds)->random(min(800, count($allClientIds)))->values()->all();

        if (empty($userIds)) {
            $this->command->warn('No hay clientes; se omite ReactionSeeder.');

            return;
        }

        $rows = [];
        $total = 0;
        $now = now();

        Work::query()->select(['_id'])->cursor()->each(function (Work $work) use (&$rows, &$total, $userIds, $now) {
            if (random_int(1, 100) > 70) {
                return;
            }

            $count = random_int(3, 35);
            $reactors = collect($userIds)->random(min($count, count($userIds)))->values();

            foreach ($reactors as $userId) {
                $rows[] = [
                    'work_id' => (string) $work->id,
                    'user_id' => $userId,
                    'type' => 'like',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;
            }

            if (count($rows) >= 3000) {
                Reaction::insert($rows);
                $rows = [];
            }
        });

        if (! empty($rows)) {
            Reaction::insert($rows);
        }

        $this->command->info("Reacciones sembradas: {$total}");
    }
}
