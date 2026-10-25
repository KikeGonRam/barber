<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea por un tiempo a la IP que manda demasiadas peticiones en pocos segundos, como fail2ban.
 *
 * - Cuenta las peticiones de cada IP en ventanas de config('abuse.window_seconds').
 * - Al pasar config('abuse.max_requests') la bloquea config('abuse.ban_seconds'); cada reincidencia
 *   en 24 h duplica el tiempo, hasta config('abuse.max_ban_seconds').
 * - Mientras dura el bloqueo responde 429 sin tocar la base de datos ni el resto de la aplicación.
 * - Deja una línea en el registro (IP_BLOQUEADA) con la IP, el país, la región y la ciudad si
 *   CloudFront los manda (cabeceras CloudFront-Viewer-*), la ruta y el agente de usuario.
 *
 * La IP viene de $request->ip(); detrás de CloudFront y el ALB solo es la del visitante con
 * TRUSTED_PROXY_HOPS=2 (ver TrustProxyChain). Sin eso todos compartirían la IP del balanceador.
 * Con varias tareas de ECS y CACHE_STORE=file cada tarea cuenta por su lado; con un almacén
 * compartido (CACHE_LIMITER_STORE) el conteo es uno solo.
 */
class BlockAbusiveIps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('abuse.enabled') || $request->is('up')) {
            return $next($request);
        }

        $ip = (string) $request->ip();

        if ($ip === '' || $this->isExempt($ip)) {
            return $next($request);
        }

        $cache = Cache::store(config('cache.limiter'));
        $banKey = "abuse:ban:{$ip}";

        if ($cache->has($banKey)) {
            return $this->blocked((int) $cache->get($banKey) - time());
        }

        $window = max(1, (int) config('abuse.window_seconds'));
        $bucket = "abuse:hits:{$ip}:".intdiv(time(), $window);
        $cache->add($bucket, 0, $window * 2);
        $hits = (int) $cache->increment($bucket);

        if ($hits <= (int) config('abuse.max_requests')) {
            return $next($request);
        }

        $seconds = $this->ban($cache, $ip);
        $this->report($request, $ip, $hits, $seconds);

        return $this->blocked($seconds);
    }

    private function isExempt(string $ip): bool
    {
        if (in_array($ip, (array) config('abuse.allowlist'), true)) {
            return true;
        }

        if (! config('abuse.skip_private')) {
            return false;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /** Guarda el bloqueo y devuelve su duración en segundos. */
    private function ban(Repository $cache, string $ip): int
    {
        $strikesKey = "abuse:strikes:{$ip}";
        $cache->add($strikesKey, 0, 86400);
        $strikes = max(1, (int) $cache->increment($strikesKey));

        $seconds = min(
            (int) config('abuse.ban_seconds') * (2 ** ($strikes - 1)),
            (int) config('abuse.max_ban_seconds'),
        );

        // El valor es la hora de fin, para poder decir cuánto falta en Retry-After.
        $cache->put("abuse:ban:{$ip}", time() + $seconds, $seconds);

        return $seconds;
    }

    private function report(Request $request, string $ip, int $hits, int $seconds): void
    {
        Log::warning('IP_BLOQUEADA', [
            'ip' => $ip,
            'peticiones_en_ventana' => $hits,
            'ventana_segundos' => (int) config('abuse.window_seconds'),
            'bloqueo_segundos' => $seconds,
            'pais' => $request->header('CloudFront-Viewer-Country'),
            'region' => $request->header('CloudFront-Viewer-Country-Region-Name'),
            'ciudad' => $request->header('CloudFront-Viewer-City'),
            'ruta' => '/'.$request->path(),
            'metodo' => $request->method(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
        ]);
    }

    private function blocked(int $seconds): Response
    {
        return response()->json(
            ['message' => 'Demasiadas solicitudes. Intenta de nuevo más tarde.'],
            429,
            ['Retry-After' => (string) max(1, $seconds), 'Cache-Control' => 'no-store'],
        );
    }
}
