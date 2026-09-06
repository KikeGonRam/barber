<?php

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsInsight;
use App\Services\Analytics\AnalyticsInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * API del centro analítico por rol (Fase Analítica), puerto exacto de
 * Analytics\AnalyticsController (web): mismo AnalyticsInsightService, mismas
 * secciones/kpis/sparkFlow/visualCoverage, solo serializados a JSON en vez
 * de pasarse a una vista Blade. Ver ese controlador para el detalle de cada
 * regla de negocio (no se cambió ninguna aquí, solo se tradujo el shape).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsInsightService $insights) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user?->hasRoleName('administrador')) {
            $insights = $this->insights->forAdmin();
            $rolLabel = 'administrador';
        } elseif ($user?->hasRoleName('recepcionista')) {
            $insights = $this->insights->forReception();
            $rolLabel = 'recepcionista';
        } elseif ($user?->hasRoleName('barbero') && $user->barberProfile) {
            $insights = $this->insights->forBarber((string) $user->id, (string) $user->barberProfile->id);
            $rolLabel = 'barbero';
        } elseif ($user?->hasRoleName('cliente')) {
            $insights = $this->insights->forClient();
            $rolLabel = 'cliente';
        } else {
            $insights = collect();
            $rolLabel = 'invitado';
        }

        $secciones = [
            'resumen' => [
                'titulo' => 'Resumen ejecutivo',
                'subtitulo' => 'El pulso del negocio',
                'intro' => 'Una lectura rápida de los indicadores que más importan para decidir.',
                'tipos' => ['resumen_ejecutivo', 'acerca_de_la_analitica'],
                'acento' => 'text-sky-300',
            ],
            'operacion' => [
                'titulo' => 'Operación y equipo',
                'subtitulo' => 'Agenda, ventas e inventario',
                'intro' => 'Conoce cuándo se llena la agenda y cómo está funcionando el equipo.',
                'tipos' => ['demanda_horas_pico', 'demanda_horas_pico_propia', 'utilizacion_equipo', 'utilizacion_propia', 'engagement_muro_top', 'engagement_propio', 'calidad_pagos', 'inventario_alertas'],
                'acento' => 'text-emerald-300',
            ],
            'clientes' => [
                'titulo' => 'Clientes y ventas',
                'subtitulo' => 'Valor, fidelización y productos',
                'intro' => 'Identifica a tus mejores clientes y las oportunidades para aumentar cada visita.',
                'tipos' => ['segmentacion_clientes', 'clientes_en_riesgo', 'perfil_citas_premium', 'fidelizacion_ratio', 'tienda_pedidos', 'recomendacion_servicios', 'tambien_te_puede_interesar', 'pca_factores'],
                'acento' => 'text-violet-300',
            ],
            'prediccion' => [
                'titulo' => 'Predicción y cancelaciones',
                'subtitulo' => 'Alertas para anticiparte',
                'intro' => 'Usa el historial para anticipar cancelaciones y confirmar las citas que necesitan atención.',
                'tipos' => ['alertas_cancelacion', 'confirmacion_cancelacion_reforzada', 'clasificacion_cancelacion', 'matriz_resultados_cancelacion', 'regresion_facturacion'],
                'acento' => 'text-amber-300',
            ],
        ];
        $tiposDiagnostico = ['calidad_datos_etl', 'control_limpieza_datos'];
        $tiposAsignados = collect($secciones)->pluck('tipos')->flatten()->merge($tiposDiagnostico)->unique();

        $porSeccion = collect($secciones)->mapWithKeys(function (array $seccion, string $clave) use ($insights) {
            return [$clave => $insights->filter(fn (AnalyticsInsight $insight) => in_array($insight->tipo, $seccion['tipos'], true))->values()];
        });
        $porSeccion['resumen'] = $porSeccion['resumen']->merge(
            $insights->reject(fn (AnalyticsInsight $insight) => $tiposAsignados->contains($insight->tipo))->values()
        );
        $diagnosticoInsights = $insights->filter(fn (AnalyticsInsight $insight) => in_array($insight->tipo, $tiposDiagnostico, true))->values();
        $porTipo = $insights->keyBy('tipo');

        $visualCoverage = collect(AnalyticsInsight::VISUAL_FAMILIES)->map(function (array $familia, string $tipoVisual) use ($insights) {
            $count = $insights->filter(function (AnalyticsInsight $insight) use ($tipoVisual) {
                $visual = AnalyticsInsight::visualTypeFor($insight->tipo, $insight->grafica);

                return $visual === $tipoVisual && $insight->hasRenderableVisual();
            })->count();

            return array_merge($familia, [
                'tipo' => $tipoVisual,
                'count' => $count,
            ]);
        })->values();

        $sparkFlow = collect([
            [
                'titulo' => 'Datos listos',
                'descripcion' => 'Revisión, limpieza y calidad antes de calcular indicadores.',
                'tipos' => ['calidad_datos_etl', 'control_limpieza_datos'],
                'color' => 'success',
            ],
            [
                'titulo' => 'Operación del negocio',
                'descripcion' => 'Demanda, utilización, inventario, pagos e interacción.',
                'tipos' => ['demanda_horas_pico', 'demanda_horas_pico_propia', 'utilizacion_equipo', 'utilizacion_propia', 'engagement_muro_top', 'engagement_propio', 'calidad_pagos', 'inventario_alertas'],
                'color' => 'info',
            ],
            [
                'titulo' => 'Clientes y ventas',
                'descripcion' => 'Segmentos, clientes en riesgo, add-ons, puntos y recomendaciones.',
                'tipos' => ['segmentacion_clientes', 'clientes_en_riesgo', 'perfil_citas_premium', 'fidelizacion_ratio', 'tienda_pedidos', 'recomendacion_servicios', 'tambien_te_puede_interesar', 'pca_factores'],
                'color' => 'gold',
            ],
            [
                'titulo' => 'Predicción',
                'descripcion' => 'Cancelaciones, confirmaciones y estimaciones futuras.',
                'tipos' => ['alertas_cancelacion', 'confirmacion_cancelacion_reforzada', 'clasificacion_cancelacion', 'matriz_resultados_cancelacion', 'regresion_facturacion'],
                'color' => 'warning',
            ],
            [
                'titulo' => 'Visualización ejecutiva',
                'descripcion' => 'Resultados convertidos en gráficas legibles para decidir rápido.',
                'tipos' => $insights->filter->hasRenderableVisual()->pluck('tipo')->all(),
                'color' => 'danger',
            ],
        ])->map(function (array $paso) use ($insights) {
            $total = count($paso['tipos']);
            $done = $total > 0
                ? $insights->filter(fn (AnalyticsInsight $insight) => in_array($insight->tipo, $paso['tipos'], true))->count()
                : 0;

            return array_merge($paso, [
                'count' => $done,
                'total' => max($total, 1),
                'progress' => min(100, max(0, ($done / max($total, 1)) * 100)),
            ]);
        })->values();

        $kpi = function (array $tipos, string $label, string $detalle, string $tone) use ($porTipo): ?array {
            foreach ($tipos as $tipo) {
                if ($porTipo->has($tipo)) {
                    $insight = $porTipo->get($tipo);

                    return [
                        'label' => $label,
                        'value' => $insight->valor_destacado,
                        'detail' => $detalle,
                        'tone' => $tone,
                        'type' => $insight->tipo,
                        'graph' => $insight->grafica,
                        'message' => $insight->mensaje,
                    ];
                }
            }

            return null;
        };
        $kpis = collect([
            $kpi(['resumen_ejecutivo'], 'Ingresos acumulados', 'Servicios completados', 'gold'),
            $kpi(['utilizacion_equipo', 'utilizacion_propia'], 'Ocupación del equipo', 'Uso promedio de agenda', 'info'),
            $kpi(['clasificacion_cancelacion'], 'Cancelaciones', 'Citas que no se concretan', 'warning'),
            $kpi(['segmentacion_clientes'], 'Clientes VIP', 'Segmento de mayor valor', 'success'),
            $kpi(['clientes_en_riesgo'], 'Clientes en riesgo', 'Requieren reactivación', 'danger'),
        ])->filter()->values();

        $ultimaActualizacion = $insights->max('generado_en');

        return response()->json([
            'rol_label' => $rolLabel,
            'kpis' => $kpis,
            'ultima_actualizacion' => $ultimaActualizacion?->toAtomString(),
            'secciones' => collect($secciones)->map(fn (array $seccion, string $clave) => [
                'titulo' => $seccion['titulo'],
                'subtitulo' => $seccion['subtitulo'],
                'intro' => $seccion['intro'],
                'acento' => $seccion['acento'],
                'insights' => $this->serializeInsights($porSeccion[$clave]),
            ]),
            'diagnostico_insights' => $this->serializeInsights($diagnosticoInsights),
            'spark_flow' => $sparkFlow,
            'visual_coverage' => $visualCoverage,
        ]);
    }

    /**
     * Serializa insights a JSON incluyendo la gráfica cruda (Spark ya la
     * entrega lista para visualizar) — a diferencia de
     * AnalyticsInsight::toDashboardCardArray(), usado por los 4 dashboards
     * por rol, que omite la gráfica a propósito porque esas tarjetas nunca
     * muestran Chart.js, solo el valor destacado.
     */
    private function serializeInsights(Collection $insights): Collection
    {
        return $insights->map(fn (AnalyticsInsight $insight) => [
            'tipo' => $insight->tipo,
            'titulo' => $insight->titulo,
            'mensaje' => $insight->mensaje,
            'valor_destacado' => $insight->valor_destacado,
            'color' => $insight->color ?: 'gold',
            'grafica' => $insight->grafica,
            'visual_type' => AnalyticsInsight::visualTypeFor($insight->tipo, $insight->grafica),
            'has_renderable_visual' => $insight->hasRenderableVisual(),
            'generado_en' => optional($insight->generado_en)->toAtomString(),
        ])->values();
    }
}
