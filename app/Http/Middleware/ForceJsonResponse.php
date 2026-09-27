<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La API siempre responde JSON, aunque la petición no mande «Accept: application/json».
 * Sin esto, un error de validación en una ruta pública (p. ej. availability/slots) respondía
 * una redirección HTML que dejaba ver el puerto interno del contenedor.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
