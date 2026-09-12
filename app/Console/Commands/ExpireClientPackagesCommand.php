<?php

namespace App\Console\Commands;

use App\Services\Package\PackageService;
use Illuminate\Console\Command;

/**
 * Marca `expirado` los paquetes prepagados activos cuya fecha de vigencia ya
 * pasó. Programado diario en routes/console.php.
 */
class ExpireClientPackagesCommand extends Command
{
    protected $signature = 'packages:expire-stale';

    protected $description = 'Marca como expirados los paquetes prepagados vencidos';

    public function handle(PackageService $packages): int
    {
        $count = $packages->expireStale();

        $this->info("Paquetes expirados: {$count}");

        return self::SUCCESS;
    }
}
