<?php

namespace App\Console\Commands;

use App\Models\MobileApiToken;
use Illuminate\Console\Command;

/**
 * Comando de mantenimiento que borra los tokens de la API móvil (MobileApiToken)
 * cuya fecha de expiración ya pasó. Se ejecuta automáticamente vía el scheduler
 * (Schedule::command('tokens:clean-expired')->daily()) una vez al día.
 */
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
     * Borra en un solo query masivo todos los tokens con expires_at en el pasado.
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
