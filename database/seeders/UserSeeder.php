<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Siembra ÚNICAMENTE la colección `users` (además del administrador, que vive
 * en AdminUserSeeder por ser un caso especial):
 *   - 1 recepcionista
 *   - 25 usuarios con rol barbero
 *   - 1000 usuarios con rol cliente
 *
 * No crea perfiles de Barber ni Client — eso lo hacen BarberSeeder y
 * ClientSeeder respectivamente, después de que estos usuarios existan.
 *
 * Ejecutar DESPUÉS de RolePermissionSeeder y AdminUserSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Recepcionista ────────────────────────────────────────────────────
        $recep = User::updateOrCreate(
            ['email' => 'recepcion@urbanblade.com'],
            [
                'name'              => 'Recepción UrbanBlade',
                'password'          => Hash::make(env('RECEP_PASSWORD', 'Recep@Urban2025!')),
                'email_verified_at' => now(),
            ]
        );
        if (! $recep->hasRole('recepcionista')) {
            $recep->assignRole('recepcionista');
        }
        $this->command->info('  ✓ Recepcionista creada');

        // ── 25 usuarios barbero ──────────────────────────────────────────────
        for ($i = 1; $i <= 25; $i++) {
            $user = User::updateOrCreate(
                ['email' => "barbero{$i}@urbanblade.com"],
                [
                    'name'              => fake('es_MX')->name('male'),
                    'password'          => Hash::make(env('BARBER_PASSWORD', 'Barber@Urban2025!')),
                    'email_verified_at' => now(),
                ]
            );
            if (! $user->hasRole('barbero')) {
                $user->assignRole('barbero');
            }
        }
        $this->command->info('  ✓ 25 usuarios barbero creados');

        // ── 1000 usuarios cliente (en lotes para eficiencia de memoria) ───────
        $this->command->info('  Creando 1000 usuarios cliente...');
        $batch = 100;
        $total = 1000;

        for ($batch_i = 0; $batch_i < ($total / $batch); $batch_i++) {
            $start = $batch_i * $batch + 1;
            $end   = $start + $batch - 1;

            DB::reconnect(); // evita timeouts de socket en lotes largos

            for ($i = $start; $i <= $end; $i++) {
                $user = User::updateOrCreate(
                    ['email' => "cliente{$i}@urbanblade.com"],
                    [
                        'name'              => fake('es_MX')->name(),
                        'password'          => Hash::make('Cliente@Urban2025!'),
                        'email_verified_at' => now(),
                    ]
                );
                if (! $user->hasRole('cliente')) {
                    $user->assignRole('cliente');
                }
            }
            $this->command->info("    lote {$batch_i}: usuarios cliente {$start}–{$end} OK");
        }

        $this->command->info('  ✓ 1000 usuarios cliente creados');
    }
}
