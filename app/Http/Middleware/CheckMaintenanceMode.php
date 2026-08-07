<?php

namespace App\Http\Middleware;

use App\Models\BarbershopSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso al sistema cuando el modo mantenimiento está activado en
 * la configuración de la barbería (BarbershopSetting), dejando pasar solo
 * landing pública, rutas públicas, login/logout y al administrador. Se
 * registra al final del grupo "web" en bootstrap/app.php, por lo que corre
 * en todas las rutas web (no en api/*).
 */
class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $setting = BarbershopSetting::cached();
        } catch (\Throwable) {
            return $next($request);
        }

        // 1. Si el mantenimiento está DESACTIVADO, dejar pasar normalmente
        if (! $setting || ! $setting->maintenance_mode) {
            if ($request->routeIs('maintenance')) {
                return redirect('/');
            }

            return $next($request);
        }

        // 2. SI EL MANTENIMIENTO ESTÁ ACTIVO:

        // A. Permitir siempre la Landing Page y rutas públicas
        if ($request->is('/') ||
            $request->routeIs('barbers.public.show') ||
            $request->routeIs('services.public.index') ||
            $request->is('chatbot/*')) {
            return $next($request);
        }

        $user = auth()->user();

        // B. El ADMINISTRADOR es inmune en todo el sistema
        // NOTA: aquí se usa hasRole() (Spatie) en vez de hasRoleName(), que es
        // el método recomendado en el resto del proyecto para Mongo (ver el
        // comentario en NavigationMenu::sections()). Queda como está porque
        // no se debe cambiar comportamiento en esta pasada de comentarios.
        if ($user && $user->hasRole('administrador')) {
            return $next($request);
        }

        // C. Permitir Login/Logout para que puedan cerrar sesión o los admins entrar
        if ($request->is('login') || $request->is('logout')) {
            return $next($request);
        }

        // D. Todo lo demás (Dashboards internos) redirige a Mantenimiento Profesional
        if (! $request->routeIs('maintenance')) {
            return redirect()->route('maintenance');
        }

        return $next($request);
    }
}
