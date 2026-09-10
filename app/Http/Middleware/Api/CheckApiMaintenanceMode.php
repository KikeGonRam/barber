<?php

namespace App\Http\Middleware\Api;

use App\Models\BarbershopSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Equivalente de CheckMaintenanceMode (Blade) para la API: si el modo
 * mantenimiento está activo, bloquea con 503 a cualquier usuario que no sea
 * administrador. Se registra con el alias "maintenance.check" en
 * bootstrap/app.php, DESPUÉS de "mobile.auth" en el grupo de rutas
 * protegidas -- así que $request->user() ya está resuelto al mismo guard
 * "web" que usa hasRole(), igual que CheckMaintenanceMode.
 *
 * Las rutas públicas (auth/login, auth/register, catálogo, chatbot) viven
 * FUERA del grupo mobile.auth y nunca pasan por este middleware -- mismo
 * criterio que Blade, donde landing y catálogo público siguen visibles
 * durante mantenimiento aunque el resto del sistema esté bloqueado. Antes
 * de este cambio (2026-09-09), activar "Modo mantenimiento" desde
 * frontend-urban no tenía ningún efecto real: el toggle solo llegaba a
 * `BarbershopSetting`, pero nada en routes/api.php lo verificaba -- Nuxt
 * seguía funcionando igual para cualquier usuario.
 */
class CheckApiMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $setting = BarbershopSetting::cached();
        } catch (\Throwable) {
            return $next($request);
        }

        if (! $setting || ! $setting->maintenance_mode) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $user->hasRole('administrador')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'UrbanBlade está en mantenimiento. Vuelve pronto.',
            'maintenance' => true,
        ], 503);
    }
}
