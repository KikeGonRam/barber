<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica las peticiones de la app móvil mediante un Bearer token propio
 * (tabla mobile_api_tokens, no Sanctum). Se registra con el alias
 * "mobile.auth" en bootstrap/app.php y se usa en el grupo de rutas
 * api/v1/* que requieren sesión (ver routes/api.php), excepto el endpoint
 * de login que emite el token.
 */
class AuthenticateMobileApiToken
{
    /**
     * Valida el token recibido, lo resuelve a un usuario y lo autentica en
     * el guard "web" para que el resto del request (controllers, políticas)
     * funcione igual que en la sesión web normal.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }

        // El token nunca se guarda en texto plano: se compara el hash SHA-256
        // contra lo almacenado en la BD, igual que un password hash.
        $token = MobileApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $bearerToken))
            ->first();

        if (! $token || ! $token->user || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 401);
        }

        // Se actualiza en cada request autenticado; sirve para detectar
        // tokens inactivos/abandonados desde el panel de administración.
        $token->forceFill(['last_used_at' => now()])->save();

        Auth::guard('web')->setUser($token->user);

        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }
}
