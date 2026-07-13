<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Campaign\CampaignDispatcher;
use Illuminate\Console\Command;

class DispatchDueCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch-due';

    protected $description = 'Envia las campanas de marketing programadas cuya fecha ya llego';

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
