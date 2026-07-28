<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsInsightService;
use Illuminate\View\View;

/**
 * Página dedicada de "Analítica" (separada del dashboard principal, a
 * diferencia de las tarjetas sueltas que ya viven ahí) — un solo lugar
 * donde cada rol ve, con pestañas y gráficas, TODO lo que Spark calculó
 * para él. Reutiliza el mismo AnalyticsInsightService del dashboard: aquí
 * no se agrega ninguna regla nueva de "quién ve qué", solo una vista más
 * completa de los mismos datos ya filtrados por rol.
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

        // Las cuatro primeras unidades contienen los hallazgos generados por
        // Spark. La Unidad V es el centro de visualización: reúne las gráficas
        // interactivas que vienen incluidas en esos mismos hallazgos.
        $porUnidad = $insights->groupBy('unidad');
        $visualizaciones = $insights->filter(fn ($insight) => ! empty($insight->grafica))->values();
        $porTipo = $insights->keyBy('tipo');

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

        $summaryInsights = $insights->filter(fn ($insight) => in_array($insight->tipo, [
            'resumen_ejecutivo', 'demanda_horas_pico', 'clasificacion_cancelacion',
            'segmentacion_clientes', 'clientes_en_riesgo', 'control_limpieza_datos',
            'utilizacion_equipo', 'utilizacion_propia',
        ], true))->values();
        $ultimaActualizacion = $insights->max('generado_en');

        return view('analytics.index', [
            'rolLabel' => $rolLabel,
            'insights' => $insights,
            'porUnidad' => $porUnidad,
            'visualizaciones' => $visualizaciones,
            // Cuántos de los insights de este rol traen gráfica — si es 0,
            // la vista puede saltarse por completo el bloque de Chart.js.
            'tieneGraficas' => $insights->contains(fn ($i) => ! empty($i->grafica)),
            'kpis' => $kpis,
            'summaryInsights' => $summaryInsights,
            'ultimaActualizacion' => $ultimaActualizacion,
        ]);
    }
}
