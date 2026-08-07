<?php

namespace App\Http\Middleware\Role;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de ruta que exige que el usuario autenticado tenga alguno de
 * los roles indicados como parámetro (p.ej. ->middleware('role.custom:administrador,recepcionista')).
 * Se registra con el alias "role.custom" en bootstrap/app.php y protege los
 * grupos de rutas por rol en routes/web.php. Usa hasRoleName()/roleNames()
 * en vez de hasRole() de Spatie porque en Mongo esta última puede dar falsos
 * negativos según el orden de carga del request (ver detalle en
 * app/Helpers/NavigationMenu.php).
 */
class EnsureUserHasRole
{
    /**
     * Corta la petición con 403 si no hay usuario, si el modelo no expone
     * hasRoleName(), o si ninguno de sus roles intersecta con los permitidos.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasRoleName')) {
            abort(403);
        }

        if ($user->roleNames()->intersect($roles)->isEmpty()) {
            abort(403);
        }

        return $next($request);
    }
}
