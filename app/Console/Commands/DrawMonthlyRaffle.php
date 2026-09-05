<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\RaffleResult;
use App\Notifications\Loyalty\RaffleWinNotification;
use Illuminate\Console\Command;

/**
 * Sortea mensualmente un premio (corte premium gratis) entre los clientes de
 * nivel de lealtad "vip" o "leyenda". Se ejecuta el día 1 de cada mes a las 08:00
 * vía el scheduler (Schedule::command('loyalty:draw-raffle')->monthlyOn(1, '08:00')).
 */
class DrawMonthlyRaffle extends Command
{
    protected $signature = 'loyalty:draw-raffle {--month= : Mes YYYY-MM a sortear (default: mes anterior)}';

    protected $description = 'Sorteo mensual entre clientes VIP y Leyenda';

    /**
     * Elige al azar un ganador entre los clientes elegibles del mes indicado
     * (o el mes anterior por defecto), registra el resultado y le notifica.
     */
    public function handle(): int
    {
        $mes = $this->option('month') ?? now()->subMonth()->format('Y-m');

        // Evita sortear dos veces el mismo mes si el comando se corre manualmente
        // por error o se reintenta.
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

        // Selección aleatoria simple entre la colección de elegibles.
        $ganador = $elegibles->random();

        $result = RaffleResult::create([
            'client_id' => (string) $ganador->id,
            'mes' => $mes,
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => $ganador->nivel,
            'vence_en' => now()->addDays(RaffleResult::VIGENCIA_DIAS),
        ]);

        try {
            $ganador->user?->notify(new RaffleWinNotification($result));
        } catch (\Throwable) {
            // Si falla la notificacion (ej. usuario sin canal configurado), el
            // sorteo ya quedo registrado en BD; no se revierte por un error de aviso.
        }

        $this->info("Ganador del sorteo {$mes}: {$ganador->user?->name} (nivel: {$ganador->nivel})");
        $this->info("Premio: {$result->premio}");

        return self::SUCCESS;
    }
}
