<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Services\Ai\BriefingService;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Inteligencia artificial
 *
 * Resumen del día en lenguaje natural para el inicio de recepción y de administración.
 */
class BriefingController extends Controller
{
    public function __construct(
        private readonly BriefingService $briefings,
        private readonly DashboardService $dashboard,
    ) {}

    /**
     * Resumen del día.
     *
     * Responde al instante: el último resumen de la IA local (source "ia") o, si todavía no hay
     * uno, una versión armada por reglas con los mismos números (source "reglas"). La IA genera el
     * siguiente en segundo plano, después de responder, así que nunca hace esperar a la pantalla.
     *
     * @authenticated
     *
     * @response 200 {"data": {"text": "Hoy hay 8 citas en la agenda. Conviene atender primero: 2 por cobrar, 1 pedido por entregar.", "source": "reglas", "generated_at": null}}
     * @response 403 {"message": "No autorizado."}
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->hasRole('administrador');
        abort_unless($isAdmin || $user?->hasRole('recepcionista'), 403, 'No autorizado.');

        if ($isAdmin) {
            $k = $this->dashboard->adminMetrics()['kpis'];
            $reception = $this->dashboard->receptionistMetrics()['kpis'];
            $facts = [
                'citas_hoy' => $k['appointments_today'] ?? 0,
                'citas_semana' => $k['appointments_week'] ?? 0,
                'ingresos_hoy_mxn' => $k['income_today'] ?? 0,
                'ingresos_mes_mxn' => $k['income_month'] ?? 0,
                'crecimiento_ingresos_pct' => $k['income_growth'] ?? 0,
                'clientes_nuevos_mes' => $k['new_clients'] ?? 0,
                'barbero_con_mas_ingresos' => $k['top_barber_name'] ?? null,
                'citas_por_cobrar' => $reception['pending_payments'] ?? 0,
                'pedidos_por_entregar' => $reception['pending_orders'] ?? 0,
                'productos_con_stock_bajo' => $reception['low_stock_count'] ?? 0,
            ];
        } else {
            $k = $this->dashboard->receptionistMetrics()['kpis'];
            $facts = [
                'citas_hoy' => $k['appointments_today'] ?? 0,
                'citas_por_cobrar' => $k['pending_payments'] ?? 0,
                'cobrado_hoy_mxn' => $k['collected_today'] ?? 0,
                'clientes_nuevos_hoy' => $k['new_clients_today'] ?? 0,
                'pedidos_por_entregar' => $k['pending_orders'] ?? 0,
                'productos_con_stock_bajo' => $k['low_stock_count'] ?? 0,
            ];
        }

        return response()->json([
            'data' => $this->briefings->briefing($isAdmin ? 'admin' : 'recepcion', $facts),
        ]);
    }
}
