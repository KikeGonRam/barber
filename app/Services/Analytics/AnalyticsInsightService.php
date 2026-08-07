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

    /**
     * Insights globales para el rol administrador. Lee de cache (10 min).
     */
    public function forAdmin(): Collection
    {
        return $this->porRol('administrador');
    }

    /**
     * Insights globales para el rol recepcionista. Lee de cache (10 min).
     */
    public function forReception(): Collection
    {
        return $this->porRol('recepcionista');
    }

    /**
     * Insights genéricos visibles para cualquier cliente. Lee de cache (10 min).
     */
    public function forClient(): Collection
    {
        return $this->porRol('cliente');
    }

    /**
     * Devuelve un resumen corto y accionable para el dashboard operativo.
     * La página /analitica conserva el detalle completo; aquí se priorizan
     * los hallazgos que ayudan a decidir qué hacer hoy, sin saturar el panel.
     */
    public function highlightsForDashboard(Collection $insights, string $role, int $limit = 3): Collection
    {
        $priority = match ($role) {
            'administrador' => [
                'clientes_en_riesgo', 'alertas_cancelacion', 'confirmacion_cancelacion_reforzada', 'inventario_alertas',
                'regresion_facturacion', 'segmentacion_clientes', 'utilizacion_equipo',
            ],
            'recepcionista' => [
                'inventario_alertas', 'alertas_cancelacion', 'clientes_en_riesgo', 'demanda_horas_pico',
                'utilizacion_equipo', 'tienda_pedidos', 'segmentacion_clientes',
            ],
            'barbero' => [
                'demanda_horas_pico_propia', 'utilizacion_propia', 'engagement_propio',
                'demanda_horas_pico',
            ],
            'cliente' => ['tambien_te_puede_interesar'],
            default => [],
        };

        $order = array_flip($priority);

        return $insights
            ->sortBy(fn (AnalyticsInsight $insight) => $order[$insight->tipo] ?? PHP_INT_MAX)
            ->take($limit)
            ->values();
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

        if (request()->boolean('actualizar')) {
            Cache::forget($cacheKey);
        }

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

    /**
     * Consulta (con cache de 10 min, invalidable via ?actualizar=1) los
     * insights de un rol sin recorte privado por barbero.
     */
    private function porRol(string $rol): Collection
    {
        if (request()->boolean('actualizar')) {
            Cache::forget("analytics_insights.{$rol}");
        }

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
