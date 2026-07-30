<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsInsight;
use App\Services\Analytics\AnalyticsInsightService;
use Illuminate\View\View;

/**
 * Centro analítico por rol.
 *
 * Reutiliza los mismos resultados filtrados por AnalyticsInsightService y
 * solo organiza su presentación para que el administrador, barbero,
 * recepcionista o cliente vea información accionable sin cambiar la lógica.
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsInsightService $insights) {}

    public function index(): View
    {
        $user = request()->user();

        // Mismo patrón de branching por rol que DashboardController — cada
        // rol solo recibe SU propio recorte de insights (ver el servicio).
        if ($user->hasRoleName('administrador')) {
            $insights = $this->insights->forAdmin();
            $rolLabel = 'administrador';
        } elseif ($user->hasRoleName('recepcionista')) {
            $insights = $this->insights->forReception();
            $rolLabel = 'recepcionista';
        } elseif ($user->hasRoleName('barbero') && $user->barberProfile) {
            $insights = $this->insights->forBarber((string) $user->id, (string) $user->barberProfile->id);
            $rolLabel = 'barbero';
        } elseif ($user->hasRoleName('cliente')) {
            $insights = $this->insights->forClient();
            $rolLabel = 'cliente';
        } else {
            $insights = collect();
            $rolLabel = 'invitado';
        }

        // Cada sección agrupa los hallazgos por intención de negocio, no por
        // nombre académico, para que el usuario final entienda qué puede hacer.
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
            return [$clave => $insights->filter(fn ($insight) => in_array($insight->tipo, $seccion['tipos'], true))->values()];
        });
        // Si Spark agrega un insight nuevo, se conserva en el resumen en vez
        // de perderlo por no tener todavía una categoría visual.
        $porSeccion['resumen'] = $porSeccion['resumen']->merge(
            $insights->reject(fn ($insight) => $tiposAsignados->contains($insight->tipo))->values()
        );
        $diagnosticoInsights = $insights->filter(fn ($insight) => in_array($insight->tipo, $tiposDiagnostico, true))->values();
        $porTipo = $insights->keyBy('tipo');

        $visualCoverage = collect(AnalyticsInsight::VISUAL_FAMILIES)->map(function (array $familia, string $tipoVisual) use ($insights) {
            $count = $insights->filter(function ($insight) use ($tipoVisual) {
                $visual = AnalyticsInsight::visualTypeFor($insight->tipo, $insight->grafica);

                return $visual === $tipoVisual && ! empty($insight->grafica);
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
                'tipos' => $insights->filter(fn ($insight) => ! empty($insight->grafica))->pluck('tipo')->all(),
                'color' => 'danger',
            ],
        ])->map(function (array $paso) use ($insights) {
            $total = count($paso['tipos']);
            $done = $total > 0
                ? $insights->filter(fn ($insight) => in_array($insight->tipo, $paso['tipos'], true))->count()
                : 0;

            return array_merge($paso, [
                'count' => $done,
                'total' => max($total, 1),
                'progress' => min(100, max(0, ($done / max($total, 1)) * 100)),
            ]);
        })->values();

        // KPIs ejecutivos construidos con los mismos resultados publicados por
        // Spark. Los candidatos alternos permiten que cada rol vea su métrica
        // equivalente aunque tenga un recorte distinto de información.
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

        return view('analytics.index', [
            'rolLabel' => $rolLabel,
            'insights' => $insights,
            'kpis' => $kpis,
            'ultimaActualizacion' => $ultimaActualizacion,
            'secciones' => $secciones,
            'porSeccion' => $porSeccion,
            'diagnosticoInsights' => $diagnosticoInsights,
            'sparkFlow' => $sparkFlow,
            'visualCoverage' => $visualCoverage,
        ]);
    }
}
