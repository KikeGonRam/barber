<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsInsight;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Traduce la colección `analytics_insights` (llenada por Spark, ver el
 * modelo AnalyticsInsight para el detalle completo) al recorte que le
 * corresponde a cada rol.
 *
 * Regla de negocio central de este servicio: NADIE ve más de lo que le toca.
 *   - Administrador y Recepción ven los insights "globales" de su rol.
 *   - Un Barbero SOLO ve sus propios insights privados (nunca los de otro
 *     barbero) + los insights de barbero que son genéricos (sin id, aplican
 *     a todos por igual, ej. la nota de "Acerca de la analítica").
 *   - Un Cliente ve solo los insights genéricos marcados para 'cliente'
 *     (en esta primera versión no hay insights personalizados por cliente,
 *     todos son iguales para cualquier cliente que los vea).
 *
 * Por qué se cachea 10 minutos: Spark solo corre una vez al día (o cuando el
 * equipo lo ejecuta manualmente antes de una demo), así que no tiene sentido
 * pegarle a Mongo en cada carga de dashboard — el dato no cambia tan seguido.
 */
class AnalyticsInsightService
{
    private const CACHE_TTL_SEGUNDOS = 600;

    public function forAdmin(): Collection
    {
        return $this->porRol('administrador');
    }

    public function forReception(): Collection
    {
        return $this->porRol('recepcionista');
    }

    public function forClient(): Collection
    {
        return $this->porRol('cliente');
    }

    /**
     * Un barbero puede tener DOS tipos de id distintos según de qué
     * colección viene el insight (ver el comentario largo en
     * AnalyticsInsight y en el script de Python): $barberUserId es
     * users._id (para insights del muro social) y $barberProfileId es
     * barbers._id (para insights de agenda/utilización). Se pasan ambos
     * para no dejar fuera ninguno de los dos tipos.
     */
    public function forBarber(string $barberUserId, ?string $barberProfileId): Collection
    {
        $cacheKey = "analytics_insights.barbero.{$barberUserId}.{$barberProfileId}";

        return Cache::remember($cacheKey, self::CACHE_TTL_SEGUNDOS, function () use ($barberUserId, $barberProfileId) {
            return AnalyticsInsight::query()
                ->where('roles', 'barbero')
                ->get()
                ->filter(function (AnalyticsInsight $insight) use ($barberUserId, $barberProfileId) {
                    $esPrivado = $insight->barbero_user_id || $insight->barbero_perfil_id;

                    if (! $esPrivado) {
                        // Insight de barbero sin id específico (ej. "Acerca de
                        // la analítica") — es genérico, todos los barberos lo ven.
                        return true;
                    }

                    return $insight->barbero_user_id === $barberUserId
                        || ($barberProfileId && $insight->barbero_perfil_id === $barberProfileId);
                })
                ->values();
        });
    }

    private function porRol(string $rol): Collection
    {
        return Cache::remember("analytics_insights.{$rol}", self::CACHE_TTL_SEGUNDOS, function () use ($rol) {
            return AnalyticsInsight::query()
                ->where('roles', $rol)
                // administrador/recepcion/cliente no tienen recorte privado por
                // barbero, así que si alguna vez se agrega un insight con
                // barbero_user_id/barbero_perfil_id y por error también incluye
                // este rol en `roles`, lo excluimos aquí para no filtrar datos
                // de un barbero específico a un rol que no debería verlos.
                ->whereNull('barbero_user_id')
                ->whereNull('barbero_perfil_id')
                ->orderByDesc('generado_en')
                ->get();
        });
    }
}
