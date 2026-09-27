<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea la inyección NoSQL: ninguna llave de la petición (JSON, formulario o URL, a cualquier
 * profundidad) puede empezar con «$». Varios filtros pasan el parámetro directo a la consulta
 * (p. ej. where('estado', $request->query('estado'))); con ?estado[$ne]=x Mongo lo tomaba como
 * operador y devolvía todo. Ningún campo legítimo de la API usa llaves con «$». (T054, HT-19)
 */
class RejectMongoOperators
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->hasOperatorKey($request->query->all()) || $this->hasOperatorKey($request->all())) {
            return response()->json(['message' => 'Solicitud no válida.'], 422);
        }

        return $next($request);
    }

    /** @param array<mixed> $data */
    private function hasOperatorKey(array $data, int $depth = 0): bool
    {
        if ($depth > 32) {
            return true;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '$')) {
                return true;
            }
            if (is_array($value) && $this->hasOperatorKey($value, $depth + 1)) {
                return true;
            }
        }

        return false;
    }
}
