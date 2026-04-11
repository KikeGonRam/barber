<?php

namespace App\Console\Commands;

use App\Models\MobileApiToken;
use Illuminate\Console\Command;

class CleanExpiredTokens extends Command
{
    /**
     * El nombre y firma del comando de consola.
     *
     * @var string
     */
    protected $signature = 'tokens:clean-expired';

    /**
     * La descripción del comando de consola.
     *
     * @var string
     */
    protected $description = 'Elimina tokens de API expirados de la base de datos';

    /**
     * Ejecutar el comando de consola.
     */
    public function handle(): int
    {
        $this->info('Iniciando limpieza de tokens expirados...');

        $deleted = MobileApiToken::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Se eliminaron {$deleted} tokens expirados.");

        return Command::SUCCESS;
    }
}
