<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
            // Una campana con datos raros o un fallo puntual no debe tumbar
            // el resto del batch -- las demas campanas vencidas en esta
            // corrida deben seguir enviandose (antes, una excepcion aqui
            // abortaba el comando completo, dejando sin enviar cualquier
            // campana vencida despues de la que fallo).
            try {
                $count = $dispatcher->dispatch($campaign);
                $this->info("Campana '{$campaign->titulo}' enviada a {$count} cliente(s).");
            } catch (\Throwable $e) {
                Log::warning('Fallo envio de campana', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Campana '{$campaign->titulo}' fallo al enviarse: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
