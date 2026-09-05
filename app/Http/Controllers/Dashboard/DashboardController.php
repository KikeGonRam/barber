<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsInsight;
use App\Models\Appointment;
use App\Models\BarbershopSetting;
use App\Models\Order;
use App\Models\Service;
use App\Services\Analytics\AnalyticsInsightService;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Dashboard principal, con una vista distinta por rol (administrador, barbero,
 * recepcionista, cliente) renderizada desde la misma plantilla 'dashboard'
 * pero con datos y flags (adminMode/isBarberMode/...) específicos de cada rol.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly AnalyticsInsightService $analyticsInsightService,
    ) {}

    // Determina el rol del usuario autenticado y arma el payload de métricas correspondiente.
    public function index(): View|InertiaResponse
    {
        $user = request()->user();

        if (! $user) {
            return view('dashboard', ['adminMode' => false]);
        }

        if ($user->hasRoleName('administrador')) {
            $data = $this->dashboardService->adminMetrics();
            $setting = BarbershopSetting::cached()
                ?? BarbershopSetting::create(['nombre' => config('app.name'), 'politica_cancelacion' => 24]);

            $todayAppointments = Appointment::with(['client.user', 'barber.user', 'service'])
                ->whereDate('fecha', Carbon::today())
                ->orderBy('hora_inicio')
                ->limit(6)
                ->get();

            $recentAppointments = Appointment::with(['client.user', 'barber.user', 'service'])
                ->orderByDesc('fecha')->orderByDesc('hora_inicio')
                ->limit(8)
                ->get();
            $sparkInsights = $this->analyticsInsightService->forAdmin();

            return view('dashboard', [
                'adminMode' => true,
                'isBarberMode' => false,
                'isReceptionMode' => false,
                'isClientMode' => false,
                'kpis' => $data['kpis'],
                'incomeChart' => $data['income_chart'],
                'servicesChart' => $data['services_chart'],
                'barberPerformance' => $data['barber_performance'],
                'clientTrends' => $data['client_trends'],
                'chatbotTelemetry' => $data['chatbot_telemetry'] ?? [],
                'maintenanceMode' => $setting?->maintenance_mode ?? false,
                'todayAppointments' => $todayAppointments,
                'recentAppointments' => $recentAppointments,
                'insights' => $this->analysisInsights(),
                'sparkInsights' => $sparkInsights,
                'sparkHighlights' => $this->analyticsInsightService->highlightsForDashboard($sparkInsights, 'administrador'),
            ]);
        }

        if ($user->hasRoleName('barbero') && $user->barberProfile) {
            $barberId = (string) $user->barberProfile->id;
            $data = $this->dashboardService->barberMetrics($barberId);

            // Sin cache: la agenda de hoy y las solicitudes por aprobar deben
            // sentirse en tiempo real (el barbero aprueba/rechaza desde aquí).
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
            $sparkInsights = $this->analyticsInsightService->forBarber((string) $user->id, $barberId);

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => true,
                'isReceptionMode' => false,
                'isClientMode' => false,
                'kpis' => $data['kpis'],
                'performanceChart' => $data['performance_chart'],
                'servicesChart' => $data['services_chart'],
                'chatbotTelemetry' => [],
                'barberToday' => $barberToday,
                'barberPending' => $barberPending,
                // El barbero solo recibe SUS PROPIOS insights (nunca los de
                // otro barbero) — ver AnalyticsInsightService::forBarber().
                'sparkInsights' => $sparkInsights,
                'sparkHighlights' => $this->analyticsInsightService->highlightsForDashboard($sparkInsights, 'barbero'),
            ]);
        }

        if ($user->hasRoleName('recepcionista')) {
            $data = $this->dashboardService->receptionistMetrics();
            $sparkInsights = $this->analyticsInsightService->forReception();
            $pendingOrdersList = $data['pending_orders_list'] ?? collect();

            // Migrado a Inertia+Vue (ver .claude/skills/inertia-vue-migration/SKILL.md,
            // Fase 4). Los otros 3 roles siguen en Blade hasta su propia fase.
            return Inertia::render('Dashboard/Recepcion', [
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
                    ->map(fn (AnalyticsInsight $insight) => $insight->toDashboardCardArray()),
            ]);
        }

        if ($user->hasRoleName('cliente')) {
            $client = $user->clientProfile;
            if (! $client) {
                $client = $user->clientProfile()->create();
            }

            $data = $this->dashboardService->clientMetrics((string) $client->id);
            $sparkInsights = $this->analyticsInsightService->forClient();

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => false,
                'isReceptionMode' => false,
                'isClientMode' => true,
                'kpis' => $data['kpis'],
                'loyalty' => $data['loyalty'],
                'nextAppointment' => $data['next_appointment'],
                'chatbotTelemetry' => [],
                'visit_chart' => $data['visit_chart'],
                'sparkInsights' => $sparkInsights,
                'sparkHighlights' => $this->analyticsInsightService->highlightsForDashboard($sparkInsights, 'cliente'),
            ]);
        }

        return view('dashboard', [
            'adminMode' => false,
            'isBarberMode' => false,
            'isReceptionMode' => false,
            'isClientMode' => false,
        ]);
    }

    /**
     * Hallazgos de negocio calculados en vivo — la versión operativa de los
     * análisis del proyecto Spark (UrbanBlade Analytics), para que el admin
     * los vea donde toma decisiones y no solo en el dashboard académico.
     * Cacheado 10 min: son agregaciones sobre ~12k citas.
     */
    private function analysisInsights(): array
    {
        return Cache::remember('dashboard_insights', 600, function () {
            $insights = [];

            // 1. Segmento premium: el servicio más caro concentra poco volumen
            //    pero un ticket muy superior (hallazgo KMeans, Unidad IV).
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

            // 2. Cancelaciones: mes en curso vs mes anterior.
            $iniMes = now()->startOfMonth();
            $iniPrev = now()->subMonthNoOverflow()->startOfMonth();
            $finPrev = $iniMes->copy()->subSecond();
            $mesTot = Appointment::where('fecha', '>=', $iniMes)->count();
            $mesCan = Appointment::where('fecha', '>=', $iniMes)->where('estado', 'cancelada')->count();
            $prevTot = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->count();
            $prevCan = Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->where('estado', 'cancelada')->count();
            // Umbral minimo de muestra: con pocas citas un solo caso mueve el
            // porcentaje de forma dramatica y engañosa (ej. 1 de 2 = "50%").
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

            // 3. Hora pico de los últimos 30 días (hallazgo de demanda, Unidad III).
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
