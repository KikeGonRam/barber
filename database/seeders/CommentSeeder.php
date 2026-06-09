<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Comment;
use App\Models\Work;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $works = Work::all();
        $clients = Client::with('user')->get();

        if ($works->isEmpty() || $clients->isEmpty()) {
            $this->command->warn('CommentSeeder: se necesitan works y clientes. Ejecuta WorkSeeder y DemoDataSeeder primero.');
            return;
        }

        $comments = [
            'Excelente trabajo, quedé muy satisfecho!' ,
            'El barbero es muy profesional y detallista.',
            'Me encantó el resultado, regreso la próxima semana.',
            'Corte perfectamente ejecutado, lo recomiendo.',
            'Muy buen servicio y atención al cliente.',
            'El fade quedó increíble, gracias!',
            'Trabajo de alta calidad, vale cada peso.',
            'Gran habilidad y precisión en el corte.',
            'El ambiente es genial y el trabajo excelente.',
            'Súper contento con mi nuevo estilo.',
        ];

        foreach ($works as $work) {
            $numComments = rand(2, 6);
            $sampledClients = $clients->random(min($numComments, $clients->count()));

            foreach ($sampledClients as $client) {
                Comment::create([
                    'work_id' => (string) $work->id,
                    'user_id' => (string) $client->user_id,
                    'comment' => fake()->randomElement($comments),
                    'rating'  => rand(4, 5),
                ]);
            }
        }

        $this->command->info('CommentSeeder: comentarios creados para '.count($works).' works.');
    }
}
