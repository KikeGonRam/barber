<?php

namespace App\Http\Middleware\Role;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de ruta que exige que el usuario autenticado tenga al menos uno
 * de los permisos indicados como parámetro (p.ej. ->middleware('permission.custom:citas.editar')).
 * Se registra con el alias "permission.custom" en bootstrap/app.php y se
 * aplica directamente en routes/web.php a rutas puntuales que necesitan
 * granularidad más fina que un simple rol.
 */
class EnsureUserHasPermission
{
    /**
     * Corta la petición con 403 si no hay usuario, si el modelo no expone
     * hasAnyPermission() (guard de seguridad por si cambia el modelo User),
     * o si el usuario no tiene ninguno de los permisos requeridos.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! method_exists($user, 'hasAnyPermission')) {
            abort(403);
        }

        if (! $user->hasAnyPermission($permissions)) {
            abort(403);
        }

        return $next($request);
    }
}
