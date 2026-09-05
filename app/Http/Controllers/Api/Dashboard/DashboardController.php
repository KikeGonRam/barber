<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\User;
use App\Services\Analytics\AnalyticsInsightService;
use App\Services\Dashboard\DashboardService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Member\MemberCardService;
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
        private readonly MemberCardService $memberCardService,
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
                'data' => $this->clientPayload($user),
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

    /**
     * Mismo shape curado que Dashboard\DashboardController::index() (rama
     * cliente) — ver receptionistPayload() arriba para el porqué de este
     * patrón. `member.downloadUrl` se omite (null): la tarjeta descargable
     * en PDF vive en una ruta web de Blade que no existe en este frontend
     * todavía, así que el botón "Descargar tarjeta" simplemente no se
     * renderiza (MembershipCard.vue ya lo hace condicional a que exista).
     */
    private function clientPayload(User $user): array
    {
        $client = $user->clientProfile;
        $data = $this->dashboardService->clientMetrics((string) $client->id);
        $sparkInsights = $this->analyticsInsightService->forClient();
        $loyalty = $data['loyalty'];
        $nivel = $loyalty['nivel'];
        $nextNivel = $loyalty['next_nivel'];
        $wonRaffle = $loyalty['won_raffle'];
        // Recomendación aplicada: el cliente ve una acción útil, no
        // análisis crudo — mismo criterio que la versión Inertia.
        $clienteReco = collect($sparkInsights)->firstWhere('tipo', 'tambien_te_puede_interesar');

        // Campos de fecha en español precalculados aquí (Carbon), igual que
        // 'todayLabel' — no reimplementar formato de fechas en el frontend.
        $nextAppt = $data['next_appointment'];
        if ($nextAppt) {
            $apptAt = Carbon::parse($nextAppt['fecha'].' '.$nextAppt['hora_inicio']);
            $nextAppt['day'] = $apptAt->format('d');
            $nextAppt['monthShort'] = $apptAt->translatedFormat('M');
            $nextAppt['dateLong'] = $apptAt->translatedFormat('d F Y');
            $nextAppt['canManage'] = in_array(strtolower((string) $nextAppt['estado']), ['pendiente', 'confirmada'], true)
                && $apptAt->isFuture();
        }

        return [
            'todayLabel' => now()->translatedFormat('l d \\d\\e F, Y'),
            'kpis' => $data['kpis'],
            'nextAppointment' => $nextAppt,
            'visitChart' => $data['visit_chart'],
            'loyalty' => [
                'nivel' => $nivel,
                'nivelLabel' => LoyaltyService::LEVEL_LABELS[$nivel] ?? strtoupper($nivel),
                'puntos' => $loyalty['puntos'],
                'discountPct' => $loyalty['discount_pct'],
                'nextNivel' => $nextNivel,
                'nextNivelLabel' => $nextNivel ? (LoyaltyService::LEVEL_LABELS[$nextNivel] ?? null) : null,
                'citasFaltan' => $loyalty['citas_faltan'],
                'progressPct' => $loyalty['progress_pct'],
                'recentTransactions' => collect($loyalty['recent_transactions'])->map(fn ($tx) => [
                    'descripcion' => $tx->descripcion,
                    'puntos' => (int) $tx->puntos,
                ])->values(),
                'wonRaffle' => $wonRaffle ? [
                    'mes' => $wonRaffle->mes,
                    'premio' => $wonRaffle->premio,
                    'isExpired' => $wonRaffle->isExpired(),
                    'venceEn' => $wonRaffle->vence_en->format('d/m/Y'),
                ] : null,
            ],
            'member' => [
                'number' => $this->memberCardService->memberNumber($user),
                'since' => $this->memberCardService->memberSince($user),
                'qr' => $this->memberCardService->qrDataUri($user),
                'downloadUrl' => null,
            ],
            'recommendation' => $clienteReco ? [
                'valorDestacado' => $clienteReco->valor_destacado,
                'mensaje' => $clienteReco->mensaje,
            ] : null,
        ];
    }
}
