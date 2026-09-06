<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Igual que AuthenticateMobileApiToken, pero para rutas públicas que
 * personalizan su respuesta cuando hay un usuario autenticado (por
 * ejemplo social/feed, cuyo is_reacted/is_saved solo tiene sentido si se
 * conoce al usuario) sin exigir el token: si no viene, o es inválido, la
 * petición sigue como invitado en vez de responder 401.
 */
class OptionalMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $token = MobileApiToken::query()
                ->with('user')
                ->where('token_hash', hash('sha256', $bearerToken))
                ->first();

            if ($token && $token->user && ! ($token->expires_at && $token->expires_at->isPast())) {
                $token->forceFill(['last_used_at' => now()])->save();
                Auth::guard('web')->setUser($token->user);
                $request->attributes->set('mobile_token', $token);
            }
        }

        return $next($request);
    }
}
