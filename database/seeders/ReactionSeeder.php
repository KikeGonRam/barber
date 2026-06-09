<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Reaction;
use App\Models\Work;
use Illuminate\Database\Seeder;

class ReactionSeeder extends Seeder
{
    public function run(): void
    {
        $works = Work::all();
        $clients = Client::with('user')->get();

        if ($works->isEmpty() || $clients->isEmpty()) {
            $this->command->warn('ReactionSeeder: se necesitan works y clientes.');
            return;
        }

        foreach ($works as $work) {
            // Each work gets reactions from a random subset of clients
            $reactingClients = $clients->random(min(rand(3, 10), $clients->count()));

            foreach ($reactingClients as $client) {
                Reaction::firstOrCreate([
                    'work_id' => (string) $work->id,
                    'user_id' => (string) $client->user_id,
                ], [
                    'type' => 'like',
                ]);
            }
        }

        $this->command->info('ReactionSeeder: reacciones creadas para '.count($works).' works.');
    }
}
