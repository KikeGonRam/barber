<?php

namespace App\Http\Middleware;

use App\Models\BarbershopSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $setting = Cache::remember('barbershop_setting', 60, fn () => BarbershopSetting::first());
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
