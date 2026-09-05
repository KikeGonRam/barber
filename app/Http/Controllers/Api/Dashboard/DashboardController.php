<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Services\Analytics\AnalyticsInsightService;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del dashboard unificado.
 * Devuelve métricas distintas según el rol del usuario autenticado
 * (administrador/barbero/recepcionista/cliente), delegando el cálculo a DashboardService.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AnalyticsInsightService $analyticsInsightService,
    ) {}

    // Enruta al set de métricas correspondiente según el rol del usuario autenticado
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        if ($user->hasRole('administrador')) {
            return response()->json([
                'role' => 'administrador',
                'data' => $this->dashboardService->adminMetrics(),
            ]);
        }

        if ($user->hasRole('barbero') && $user->barberProfile) {
            return response()->json([
                'role' => 'barbero',
                'data' => $this->dashboardService->barberMetrics((string) $user->barberProfile->id),
            ]);
        }

        if ($user->hasRole('recepcionista')) {
            return response()->json([
                'role' => 'recepcionista',
                'data' => $this->receptionistPayload(),
            ]);
        }

        if ($user->hasRole('cliente') && $user->clientProfile) {
            return response()->json([
                'role' => 'cliente',
                'data' => $this->dashboardService->clientMetrics($user->clientProfile->id),
            ]);
        }

        return response()->json([
            'role' => 'guest',
            'data' => [],
        ]);
    }

    /**
     * Mismo shape curado que Dashboard\DashboardController::index() (rama
     * recepcionista) usa para Inertia::render('Dashboard/Recepcion', ...) —
     * ver .claude/skills/nuxt-migration-plan/SKILL.md, Fase 4. Se mantiene
     * en un método propio (no en DashboardService) porque el mapeo a campos
     * de presentación (cliente/servicio/barbero ya resueltos, fechas ya
     * formateadas) es responsabilidad del controlador, igual que en la
     * versión Inertia — DashboardService sigue devolviendo datos crudos.
     */
    private function receptionistPayload(): array
    {
        $data = $this->dashboardService->receptionistMetrics();
        $sparkInsights = $this->analyticsInsightService->forReception();
        $pendingOrdersList = $data['pending_orders_list'] ?? collect();

        return [
            'todayLabel' => now()->translatedFormat('l d \\d\\e F, Y'),
            'kpis' => $data['kpis'],
            'nextAppointments' => $data['next_appointments']->map(fn (Appointment $appt) => [
                'id' => (string) $appt->id,
                'hora_inicio' => $appt->hora_inicio,
                'cliente' => $appt->client?->user?->name ?? 'Cliente',
                'servicio' => $appt->service?->nombre ?? '—',
                'barbero' => $appt->barber?->user?->name ?? '—',
            ]),
            'pendingOrders' => $pendingOrdersList->map(fn (Order $order) => [
                'id' => (string) $order->id,
                'folio' => $order->folio,
                'cliente' => $order->client?->user?->name ?? 'Cliente',
                'creadoEn' => optional($order->created_at)->translatedFormat('d M, H:i'),
                'itemsCount' => count($order->items ?? []),
                'total' => (float) $order->total,
            ]),
            'flowChart' => $data['flow_chart'],
            'sparkHighlights' => $this->analyticsInsightService
                ->highlightsForDashboard($sparkInsights, 'recepcionista')
                ->map(fn ($insight) => $insight->toDashboardCardArray())
                ->values(),
        ];
    }
}
