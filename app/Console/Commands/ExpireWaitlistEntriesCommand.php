<?php

namespace App\Console\Commands;

use App\Services\Appointment\WaitlistService;
use Illuminate\Console\Command;

/**
 * Marca `expirado` las anotaciones de lista de espera cuya fecha ya pasó
 * (activo o notificado, nunca llegaron a reservar). Programado diario en
 * routes/console.php.
 */
class ExpireWaitlistEntriesCommand extends Command
{
    protected $signature = 'waitlist:expire-stale';

    protected $description = 'Marca como expiradas las anotaciones de lista de espera cuya fecha ya pasó';

    public function handle(WaitlistService $waitlist): int
    {
        $count = $waitlist->expireStale();

        $this->info("Anotaciones de lista de espera expiradas: {$count}");

        return self::SUCCESS;
    }
}
