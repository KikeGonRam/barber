<?php

namespace App\Console\Commands;

use App\Models\MobileApiToken;
use Illuminate\Console\Command;

/**
 * Comando de mantenimiento diario que limpia tokens de la API móvil
 * (MobileApiToken) en dos casos: expirados por fecha, y "huérfanos" —
 * todavía dentro de su expires_at (hasta 6 meses, ver AuthController) pero
 * sin usarse en DIAS_INACTIVIDAD días, señal de un dispositivo perdido,
 * reinstalado o abandonado. Antes solo se limpiaba el primer caso: un token
 * robado o de un celular perdido seguía siendo válido hasta su expiración
 * natural aunque nadie lo hubiera usado en meses.
 */
class CleanExpiredTokens extends Command
{
    // Días sin uso (independientes de expires_at) antes de revocar un token
    // por inactividad. Se revoca en silencio: la próxima vez que se use la
    // app simplemente pedirá volver a iniciar sesión, sin notificación aparte.
    const DIAS_INACTIVIDAD = 90;

    protected $signature = 'tokens:clean-expired';

    protected $description = 'Elimina tokens de API expirados o inactivos por mas de '.self::DIAS_INACTIVIDAD.' dias';

    public function handle(): int
    {
        $this->info('Iniciando limpieza de tokens...');

        $expirados = MobileApiToken::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        // Nunca usado desde que se emitió (last_used_at null) o sin uso desde
        // hace mas de DIAS_INACTIVIDAD, sin importar si expires_at todavia no
        // ha llegado.
        $umbral = now()->subDays(self::DIAS_INACTIVIDAD);
        $inactivos = MobileApiToken::where(function ($query) use ($umbral) {
            $query->where('last_used_at', '<', $umbral)
                ->orWhere(function ($query) use ($umbral) {
                    $query->whereNull('last_used_at')->where('created_at', '<', $umbral);
                });
        })->delete();

        $this->info("Se eliminaron {$expirados} token(s) expirado(s) y {$inactivos} token(s) inactivo(s).");

        return Command::SUCCESS;
    }
}
