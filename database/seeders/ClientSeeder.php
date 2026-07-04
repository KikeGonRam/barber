<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Siembra ÚNICAMENTE la colección `clients` — el perfil de cliente de cada
 * usuario con rol "cliente" que ya haya creado UserSeeder.
 *
 * No calcula puntos/nivel/total_citas reales: esos campos los actualiza
 * LoyaltyTransactionSeeder una vez que existan citas históricas.
 *
 * Ejecutar DESPUÉS de UserSeeder.
 */
class ClientSeeder extends Seeder
{
    public function run(): void
    {
        // NOTA: User::role('cliente') usa un scope MorphToMany no soportado por
        // el driver mongodb/laravel-mongodb ("hybrid query constraints" — ver
        // unidad_6_caso_aplicado_laravel/02_causa_raiz_tecnica.md en el proyecto
        // spark). Se filtra en PHP con hasRole() dentro de cada chunk, en vez de
        // cargar User::all() de una sola vez — con el total real de usuarios
        // (~1000+) eso agotó el memory_limit por defecto.
        $this->command->info('  Creando perfiles de cliente...');
        $i         = 0;
        $batchIdx  = 0;

        User::query()->chunk(100, function ($chunk) use (&$i, &$batchIdx) {
            $batchIdx++;
            $creados = 0;

            foreach ($chunk as $user) {
                if (! $user->hasRole('cliente')) {
                    continue;
                }
                $i++;
                $creados++;

                Client::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'telefono'                  => fake()->numerify('+52##########'),
                        'fecha_nacimiento'          => fake()->dateTimeBetween('-65 years', '-16 years')->format('Y-m-d'),
                        'preferencias_notificacion' => [
                            'in_app'   => true,
                            'email'    => true,
                            'sms'      => false,
                            'whatsapp' => (bool) ($i % 3 === 0),
                        ],
                    ]
                );
            }

            if ($creados > 0) {
                $this->command->info("    lote {$batchIdx}: {$creados} perfiles procesados");
            }
        });

        if ($i === 0) {
            $this->command->error('ClientSeeder: no hay usuarios con rol cliente. Ejecuta UserSeeder primero.');
            return;
        }

        $this->command->info("  ✓ {$i} perfiles de cliente creados");
    }
}
