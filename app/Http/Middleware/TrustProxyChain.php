<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Resuelve la IP real del cliente detrás de una cadena fija de proxies.
 *
 * En staging la petición pasa por CloudFront → ALB → Caddy/PHP-FPM:
 *
 *   - REMOTE_ADDR es la IP privada del ALB, la misma para todo el mundo;
 *   - X-Forwarded-For llega como "<lo que mande el cliente>, <IP real>, <IP de CloudFront>":
 *     CloudFront agrega la IP del visitante y el ALB agrega la de CloudFront.
 *
 * Sin esta configuración, $request->ip() devolvía la IP del ALB para cualquier
 * visitante, y todos los límites "por IP" (throttle) se volvían globales para el
 * sitio entero.
 *
 * Con TRUSTED_PROXY_HOPS=N se confía en REMOTE_ADDR y en las últimas N-1 entradas
 * de X-Forwarded-For, que son exactamente las que escribieron nuestros propios
 * proxies. Las de más a la izquierda las puede inventar el cliente, así que nunca
 * se confía en ellas: una cabecera X-Forwarded-For falsificada no cambia la IP
 * resultante.
 *
 * No se usa trustProxies('*') porque solo confía en el que llama (el ALB) y la IP
 * resultante sería la del nodo de CloudFront, no la del visitante. Tampoco se lista
 * la red de CloudFront: son decenas de rangos que AWS cambia.
 *
 * Solo se confía en X-Forwarded-For. El ALB manda X-Forwarded-Proto: http y
 * X-Forwarded-Port: 8080 (el tramo CloudFront → ALB es HTTP); si se confiara en
 * ellas, Laravel generaría URLs con ":8080". El esquema https ya lo fuerza
 * AppServiceProvider a partir de APP_URL.
 *
 * Con 0 (valor por defecto, desarrollo local y pruebas) no se confía en ningún proxy.
 */
class TrustProxyChain extends TrustProxies
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR;

    protected function setTrustedProxyIpAddresses(Request $request): void
    {
        $hops = (int) config('app.trusted_proxy_hops', 0);

        if ($hops <= 0) {
            return;
        }

        $trusted = [(string) $request->server->get('REMOTE_ADDR')];

        if ($hops > 1) {
            $forwardedFor = array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) $request->headers->get('X-Forwarded-For', '')),
            )));

            $trusted = array_merge($trusted, array_slice($forwardedFor, -($hops - 1)));
        }

        $request->setTrustedProxies(array_values(array_unique($trusted)), $this->getTrustedHeaderNames());
    }
}
