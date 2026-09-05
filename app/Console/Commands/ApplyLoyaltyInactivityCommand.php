<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Client;
use App\Services\Loyalty\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Corre diario: baja de nivel (180+ dias sin cita completada) y caducidad de
 * puntos (365+ dias) via LoyaltyService::applyInactivityLifecycle(). Sigue el
 * mismo patron de una sola query agregada que SyncClientLoyaltyCounters, en
 * vez de N queries (una por cliente).
 */
class ApplyLoyaltyInactivityCommand extends Command
{
    protected $signature = 'loyalty:apply-inactivity {--dry-run : Solo muestra cuantos clientes cambiarian, sin escribir}';

    protected $description = 'Aplica baja de nivel y caducidad de puntos por inactividad prolongada';

    public function handle(LoyaltyService $loyalty): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Fecha de la ultima cita completada por cliente, en una sola query
        // en vez de una por cliente.
        $ultimaCitaPorCliente = Appointment::where('estado', 'completada')
            ->get(['client_id', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id)
            ->map(fn ($citas) => $citas->max('fecha'));

        $clients = Client::all();
        $this->info("Clientes a revisar: {$clients->count()}");

        $downgraded = 0;
        $expired = 0;

        foreach ($clients as $client) {
            $ultimaFecha = $ultimaCitaPorCliente->get((string) $client->id);

            // Sin cita completada nunca: se cuenta la antiguedad desde el
            // alta del cliente, no como "0 dias sin actividad".
            $referencia = $ultimaFecha
                ? Carbon::parse($ultimaFecha)
                : $client->created_at;

            if (! $referencia) {
                continue;
            }

            $diasSinVisita = (int) $referencia->diffInDays(now());

            if ($diasSinVisita < LoyaltyService::DIAS_BAJA_DE_NIVEL) {
                continue;
            }

            if ($dryRun) {
                $nivelActual = $client->getRawOriginal('nivel') ?? 'nuevo';
                $nivelCorrespondiente = LoyaltyService::levelAfterInactivity((int) $client->total_citas, $diasSinVisita);

                if ($nivelCorrespondiente !== $nivelActual) {
                    $downgraded++;
                }
                if ($diasSinVisita >= LoyaltyService::DIAS_CADUCIDAD_PUNTOS && (int) $client->puntos > 0) {
                    $expired++;
                }

                continue;
            }

            $resultado = $loyalty->applyInactivityLifecycle($client, $diasSinVisita);

            if ($resultado['downgraded']) {
                $downgraded++;
            }
            if ($resultado['points_expired']) {
                $expired++;
            }
        }

        $this->info($dryRun
            ? "{$downgraded} cliente(s) bajarian de nivel, {$expired} perderian sus puntos (dry-run, nada escrito)."
            : "{$downgraded} cliente(s) bajaron de nivel, {$expired} perdieron sus puntos.");

        return self::SUCCESS;
    }
}
