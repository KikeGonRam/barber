<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el panel de Laravel Pulse. Quien no puede verlo recibe 404, igual que cualquier ruta
 * inexistente, en vez del 403 que confirma que el panel existe (Pulse\Http\Middleware\Authorize).
 */
class PulseOrNotFound
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('viewPulse'), 404);

        return $next($request);
    }
}
