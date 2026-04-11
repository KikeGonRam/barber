<?php

namespace App\Http\Middleware;

use App\Models\MobileApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 401);
        }

        $token = MobileApiToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $bearerToken))
            ->first();

        if (! $token || ! $token->user || ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        Auth::guard('web')->setUser($token->user);

        $request->attributes->set('mobile_token', $token);

        return $next($request);
    }
}
