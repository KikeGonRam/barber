<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\RaffleResult;
use App\Notifications\LoyaltyNotification;
use Illuminate\Console\Command;

class DrawMonthlyRaffle extends Command
{
    protected $signature = 'loyalty:draw-raffle {--month= : Mes YYYY-MM a sortear (default: mes anterior)}';

    protected $description = 'Sorteo mensual entre clientes VIP y Leyenda';

    public function handle(): int
    {
        $mes = $this->option('month') ?? now()->subMonth()->format('Y-m');

        if (RaffleResult::where('mes', $mes)->exists()) {
            $this->warn("Ya existe un sorteo para {$mes}.");

            return self::FAILURE;
        }

        $elegibles = Client::whereIn('nivel', ['vip', 'leyenda'])
            ->with('user:id,name,email')
            ->get();

        if ($elegibles->isEmpty()) {
            $this->warn('No hay clientes elegibles (VIP o Leyenda).');

            return self::FAILURE;
        }

        $ganador = $elegibles->random();

        $result = RaffleResult::create([
            'client_id' => (string) $ganador->id,
            'mes' => $mes,
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => $ganador->nivel,
        ]);

        try {
            $ganador->user?->notify(new LoyaltyNotification(
                level: $ganador->nivel,
                previousLevel: $ganador->nivel,
                discount: 100,
            ));
        } catch (\Throwable) {
        }

        $this->info("Ganador del sorteo {$mes}: {$ganador->user?->name} (nivel: {$ganador->nivel})");
        $this->info("Premio: {$result->premio}");

        return self::SUCCESS;
    }
}
