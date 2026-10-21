<?php

namespace App\Http\Middleware;

use App\Observability\MongoCommandTimer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Avisa en el log de las peticiones que tardan más que OBSERVABILITY slow_request_ms, con la ruta (el patrón,
 * no la URL con identificadores ni query string), el estado y cuánto de ese tiempo se fue en MongoDB. Corre al
 * terminar la respuesta, así que no retrasa al usuario.
 */
class LogSlowRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        MongoCommandTimer::reset();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $threshold = (int) config('observability.slow_request_ms', 2000);
        $started = defined('LARAVEL_START') ? LARAVEL_START : (float) $request->server('REQUEST_TIME_FLOAT', microtime(true));
        $ms = (microtime(true) - $started) * 1000;

        if ($threshold >= 0 && $ms >= $threshold) {
            Log::warning('Petición lenta', [
                'method' => $request->method(),
                'route' => $request->route()?->uri() ?? $request->path(),
                'status' => $response->getStatusCode(),
                'ms' => round($ms),
                'mongo_commands' => MongoCommandTimer::$commands,
                'mongo_ms' => round(MongoCommandTimer::$millis),
                'memory_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);
        }

        MongoCommandTimer::reset();
    }
}
