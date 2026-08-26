<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Console\Command;

/**
 * Recalcula 'total_citas' y 'nivel' de cada cliente a partir del conteo real
 * de citas completadas en Appointment, en vez de confiar en el contador
 * acumulado por LoyaltyService::awardCitaPoints() (que puede desincronizarse
 * de los datos reales, por ejemplo tras un reseed o una migración de datos).
 * Se ejecuta a mano; no está en el scheduler.
 */
class SyncClientLoyaltyCounters extends Command
{
    protected $signature = 'clients:sync-loyalty-counters {--dry-run : Solo muestra cuántos clientes cambiarían, sin escribir}';

    protected $description = 'Recalcula total_citas y nivel de cada cliente desde el conteo real de citas completadas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Contando citas completadas por cliente...');

        // Un solo aggregate en vez de N queries (uno por cliente).
        $completedCounts = Appointment::where('estado', 'completada')
            ->get(['client_id'])
            ->groupBy(fn ($a) => (string) $a->client_id)
            ->map->count();

        $clients = Client::all(['_id', 'total_citas', 'nivel']);
        $this->info("Clientes a revisar: {$clients->count()}");

        $updated = 0;

        foreach ($clients as $client) {
            $realCount = $completedCounts->get((string) $client->id, 0);
            $realNivel = LoyaltyService::nivelFromCitas($realCount);

            $currentCount = (int) ($client->getRawOriginal('total_citas') ?? 0);
            $currentNivel = $client->getRawOriginal('nivel') ?? 'nuevo';

            if ($currentCount === $realCount && $currentNivel === $realNivel) {
                continue;
            }

            $updated++;

            if (! $dryRun) {
                $client->update(['total_citas' => $realCount, 'nivel' => $realNivel]);
            }
        }

        $this->info($dryRun
            ? "{$updated} cliente(s) tienen total_citas/nivel desincronizados (dry-run, nada escrito)."
            : "{$updated} cliente(s) actualizados.");

        return self::SUCCESS;
    }
}
