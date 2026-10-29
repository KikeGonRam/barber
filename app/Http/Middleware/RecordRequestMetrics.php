<?php

namespace App\Http\Middleware;

use App\Services\System\RequestMetrics;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suma la petición a las métricas por minuto del monitor del ingeniero. Corre al terminar la respuesta (no
 * retrasa al usuario), muestrea según observability.metrics_sample_rate y nunca rompe la petición si la caché falla.
 */
class RecordRequestMetrics
{
    public function __construct(private readonly RequestMetrics $metrics) {}

    public function handle(Request $request, \Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->metrics->enabled() || $this->ignored($request)) {
            return;
        }

        if (mt_rand() / mt_getrandmax() > $this->metrics->sampleRate()) {
            return;
        }

        $started = defined('LARAVEL_START') ? LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));

        try {
            $this->metrics->record($response->getStatusCode(), (microtime(true) - $started) * 1000);
        } catch (\Throwable) {
            // Las métricas son opcionales: una caché caída no debe afectar a la API.
        }
    }

    /** Sondeos de salud y del propio monitor no cuentan como tráfico. */
    private function ignored(Request $request): bool
    {
        return $request->is('up', 'api/up', 'api/admin/system/*');
    }
}
