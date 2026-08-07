<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use Illuminate\Console\Command;

/**
 * Busca campañas de marketing en estado "programada" cuya fecha/hora de envío
 * (programada_para) ya se cumplió y las despacha a los clientes correspondientes.
 * Se ejecuta cada 5 minutos vía el scheduler
 * (Schedule::command('campaigns:dispatch-due')->everyFiveMinutes()).
 */
class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Envia las campanas de marketing programadas cuya fecha ya llego';

    /**
     * Recorre las campañas vencidas y delega el envío real a CampaignDispatcher,
     * reportando por consola cuántos clientes recibieron cada una.
     */
    public function handle(CampaignDispatcher $dispatcher): int
    {
        $due = Campaign::where('estado', 'programada')
            ->where('programada_para', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('Sin campanas programadas vencidas.');

            return self::SUCCESS;
        }

        foreach ($due as $campaign) {
            $count = $dispatcher->dispatch($campaign);
            $this->info("Campana '{$campaign->titulo}' enviada a {$count} cliente(s).");
        }

        return self::SUCCESS;
    }
}
