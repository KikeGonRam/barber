<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\AnalyticsInsightService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
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
                'data' => $this->barberPayload($user),
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

    /**
     * Mismo shape curado que Dashboard\DashboardController::index() (rama
     * barbero) — ver receptionistPayload() arriba para el porqué de este
     * patrón. `statusUrl` de la versión Inertia no aplica aquí: Aprobar/
     * Rechazar en el frontend Nuxt llaman directo a
     * PATCH /api/v1/appointments/{id}/status con el Bearer token, así que
     * el frontend solo necesita el `id` de la cita.
     */
    private function barberPayload(User $user): array
    {
        $barberId = (string) $user->barberProfile->id;
        $data = $this->dashboardService->barberMetrics($barberId);
        $sparkInsights = $this->analyticsInsightService->forBarber((string) $user->id, $barberId);

        $barberToday = Appointment::with(['client.user', 'service'])
            ->where('barber_id', $barberId)
            ->whereDate('fecha', Carbon::today())
            ->orderBy('hora_inicio')
            ->get();

        $barberPending = Appointment::with(['client.user', 'service'])
            ->where('barber_id', $barberId)
            ->where('estado', 'pendiente')
            ->where('fecha', '>=', Carbon::today())
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->get();

        // El "siguiente" servicio del día: la primera cita de hoy que
        // todavía no terminó — mismo criterio que la versión Inertia.
        $nextAppointment = $barberToday->first(fn (Appointment $a) => in_array($a->estado, ['confirmada', 'en_proceso', 'pendiente'], true));
        $nextAppointmentId = $nextAppointment ? (string) $nextAppointment->id : null;

        return [
            'todayLabel' => now()->translatedFormat('l d \\d\\e F, Y'),
            'kpis' => $data['kpis'],
            'performanceChart' => $data['performance_chart'],
            'servicesChart' => $data['services_chart'],
            'barberToday' => $barberToday->map(fn (Appointment $appt) => [
                'id' => (string) $appt->id,
                'estado' => $appt->estado,
                'hora_inicio' => $appt->hora_inicio,
                'hora_fin' => $appt->hora_fin,
                'cliente' => $appt->client?->user?->name ?? 'Cliente',
                'servicio' => $appt->service?->nombre ?? '—',
                'isNext' => (string) $appt->id === $nextAppointmentId,
            ])->values(),
            'barberPending' => $barberPending->map(fn (Appointment $appt) => [
                'id' => (string) $appt->id,
                'fecha' => Carbon::parse($appt->fecha)->translatedFormat('d M'),
                'hora_inicio' => $appt->hora_inicio,
                'cliente' => $appt->client?->user?->name ?? 'Cliente',
                'servicio' => $appt->service?->nombre ?? '—',
            ])->values(),
            'sparkHighlights' => $this->analyticsInsightService
                ->highlightsForDashboard($sparkInsights, 'barbero')
                ->map(fn ($insight) => $insight->toDashboardCardArray())
                ->values(),
        ];
    }
}
