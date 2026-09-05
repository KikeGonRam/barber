<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\Analytics\AnalyticsInsightService;
use App\Services\Dashboard\DashboardService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Member\MemberCardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
                'data' => $this->adminPayload(),
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

    /**
     * Mismo shape curado que Dashboard\DashboardController::index() (rama
     * administrador) — ver receptionistPayload() arriba para el porqué de
     * este patrón. Omite `maintenanceMode` y los botones de mantenimiento/
     * backup del header original: sus destinos (`settings.maintenance.toggle`,
     * `backups.database.download`) son rutas web de Blade que no existen en
     * este frontend. Las predicciones IA ("Predicciones IA" en la sección
     * plegable) NO se resuelven aquí: el original necesitaba un puente
     * (getWebApiToken) porque corría bajo sesión web; este frontend ya trae
     * su propio Bearer token real desde el login, así que llama
     * GET /api/v1/admin/predictions/* directo — más simple que el original,
     * no una limitación.
     */
    private function adminPayload(): array
    {
        $data = $this->dashboardService->adminMetrics();
        $sparkInsights = $this->analyticsInsightService->forAdmin();

        $todayAppointments = Appointment::with(['client.user', 'barber.user', 'service'])
            ->whereDate('fecha', Carbon::today())
            ->orderBy('hora_inicio')
            ->limit(6)
            ->get();

        $recentAppointments = Appointment::with(['client.user', 'barber.user', 'service'])
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')
            ->limit(8)
            ->get();

        return [
            'todayLabel' => now()->translatedFormat('l d \\d\\e F, Y'),
            'kpis' => $data['kpis'],
            'incomeChart' => $data['income_chart'],
            'servicesChart' => $data['services_chart'],
            'barberPerformance' => $data['barber_performance'],
            'clientTrends' => $data['client_trends'],
            'chatbotTelemetry' => $data['chatbot_telemetry'] ?? [],
            'todayAppointments' => $todayAppointments->map(fn (Appointment $appt) => [
                'id' => (string) $appt->id,
                'estado' => $appt->estado,
                'hora_inicio' => $appt->hora_inicio,
                'hora_fin' => $appt->hora_fin,
                'cliente' => $appt->client?->user?->name ?? 'Cliente',
                'servicio' => $appt->service?->nombre ?? '—',
                'barbero' => $appt->barber?->user?->name ?? '—',
            ])->values(),
            'recentAppointments' => $recentAppointments->map(fn (Appointment $appt) => [
                'id' => (string) $appt->id,
                'estado' => $appt->estado,
                'hora_inicio' => $appt->hora_inicio,
                'fecha' => Carbon::parse($appt->fecha)->translatedFormat('d M'),
                'cliente' => $appt->client?->user?->name ?? 'Cliente',
                'barberoInicial' => mb_strtoupper(mb_substr($appt->barber?->user?->name ?? 'B', 0, 1)),
            ])->values(),
            'insights' => $this->analysisInsights(),
            'sparkHighlights' => $this->analyticsInsightService
                ->highlightsForDashboard($sparkInsights, 'administrador')
                ->map(fn ($insight) => $insight->toDashboardCardArray())
                ->values(),
        ];
    }

    /**
     * Hallazgos de negocio calculados en vivo — puerto directo de
     * Dashboard\DashboardController::analysisInsights() (Inertia). Cacheado
     * 10 min: son agregaciones sobre ~12k citas. Misma clave de caché que la
     * versión Inertia (comparten el resultado, no tiene sentido calcularlo
     * dos veces si ambas superficies siguen coexistiendo).
     */
    private function analysisInsights(): array
    {
        return Cache::remember('dashboard_insights', 600, function () {
            $insights = [];

            $premium = Service::where('activo', true)->orderByDesc('precio')->first();
            $totalCitas = Appointment::count();
            if ($premium && $totalCitas > 0) {
                $premiumCitas = Appointment::where('service_id', (string) $premium->id)->count();
                $avgTicket = (float) (Appointment::avg('precio_cobrado') ?: 1);
                $insights[] = [
                    'titulo' => 'Segmento premium',
                    'dato' => sprintf('%.1f%% de las citas', $premiumCitas / $totalCitas * 100),
                    'detalle' => sprintf(
                        '"%s" factura %.1fx el ticket promedio ($%s vs $%s) — candidato a upsell.',
                        $premium->nombre, ((float) $premium->precio) / max($avgTicket, 1),
                        number_format((float) $premium->precio, 0), number_format($avgTicket, 0)
                    ),
                ];
            }

            $iniMes = now()->startOfMonth();
            $iniPrev = now()->subMonthNoOverflow()->startOfMonth();
            $finPrev = $iniMes->copy()->subSecond();
            $mesTot = Appointment::where('fecha', '>=', $iniMes)->count();
            $mesCan = Appointment::where('fecha', '>=', $iniMes)->where('estado', 'cancelada')->count();
            $prevTot = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->count();
            $prevCan = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->where('estado', 'cancelada')->count();
            if ($mesTot >= 10 && $prevTot >= 10) {
                $tasaMes = $mesCan / $mesTot * 100;
                $tasaPrev = $prevCan / $prevTot * 100;
                $insights[] = [
                    'titulo' => 'Cancelaciones del mes',
                    'dato' => sprintf('%.1f%%', $tasaMes),
                    'detalle' => sprintf(
                        '%s vs %.1f%% el mes pasado (%d de %d citas).',
                        $tasaMes > $tasaPrev ? 'Subió' : 'Bajó', $tasaPrev, $mesCan, $mesTot
                    ),
                ];
            }

            $horas = Appointment::where('fecha', '>=', now()->subDays(30))
                ->pluck('hora_inicio')
                ->map(fn ($h) => substr((string) $h, 0, 2))
                ->countBy();
            if ($horas->isNotEmpty()) {
                $pico = $horas->sortDesc()->keys()->first();
                $insights[] = [
                    'titulo' => 'Hora pico (30 días)',
                    'dato' => "{$pico}:00",
                    'detalle' => sprintf(
                        '%d citas en esa franja — reforzar barberos ahí y promocionar horas valle.',
                        $horas[$pico]
                    ),
                ];
            }

            return $insights;
        });
    }
}
