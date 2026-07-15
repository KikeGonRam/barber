<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BarbershopSetting;
use App\Services\Dashboard\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(): View
    {
        $user = request()->user();

        if (! $user) {
            return view('dashboard', ['adminMode' => false]);
        }

        if ($user->hasRole('administrador')) {
            $data = $this->dashboardService->adminMetrics();
            $setting = BarbershopSetting::first()
                ?? BarbershopSetting::create(['nombre' => config('app.name'), 'politica_cancelacion' => 24]);

            $todayAppointments = \App\Models\Appointment::with(['client.user', 'barber.user', 'service'])
                ->whereDate('fecha', \Carbon\Carbon::today())
                ->orderBy('hora_inicio')
                ->get();

            $recentAppointments = \App\Models\Appointment::with(['client.user', 'barber.user', 'service'])
                ->orderByDesc('fecha')->orderByDesc('hora_inicio')
                ->limit(8)
                ->get();

            return view('dashboard', [
                'adminMode'          => true,
                'isBarberMode'       => false,
                'isReceptionMode'    => false,
                'isClientMode'       => false,
                'kpis'               => $data['kpis'],
                'incomeChart'        => $data['income_chart'],
                'servicesChart'      => $data['services_chart'],
                'barberPerformance'  => $data['barber_performance'],
                'clientTrends'       => $data['client_trends'],
                'chatbotTelemetry'   => $data['chatbot_telemetry'] ?? [],
                'maintenanceMode'    => $setting?->maintenance_mode ?? false,
                'todayAppointments'  => $todayAppointments,
                'recentAppointments' => $recentAppointments,
                'insights'           => $this->analysisInsights(),
            ]);
        }

        if ($user->hasRole('barbero') && $user->barberProfile) {
            $barberId = (string) $user->barberProfile->id;
            $data = $this->dashboardService->barberMetrics($barberId);

            // Sin cache: la agenda de hoy y las solicitudes por aprobar deben
            // sentirse en tiempo real (el barbero aprueba/rechaza desde aquí).
            $barberToday = \App\Models\Appointment::with(['client.user', 'service'])
                ->where('barber_id', $barberId)
                ->whereDate('fecha', \Carbon\Carbon::today())
                ->orderBy('hora_inicio')
                ->get();

            $barberPending = \App\Models\Appointment::with(['client.user', 'service'])
                ->where('barber_id', $barberId)
                ->where('estado', 'pendiente')
                ->where('fecha', '>=', \Carbon\Carbon::today())
                ->orderBy('fecha')->orderBy('hora_inicio')
                ->get();

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
            ]);
        }

        if ($user->hasRole('recepcionista')) {
            $data = $this->dashboardService->receptionistMetrics();

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => false,
                'isReceptionMode' => true,
                'isClientMode' => false,
                'kpis' => $data['kpis'],
                'nextAppointments' => $data['next_appointments'],
                'pending_orders_list' => $data['pending_orders_list'] ?? collect(),
                'flow_chart' => $data['flow_chart'],
                'chatbotTelemetry' => [],
            ]);
        }

        if ($user->hasRole('cliente')) {
            $client = $user->clientProfile;
            if (! $client) {
                $client = $user->clientProfile()->create();
            }

            $data = $this->dashboardService->clientMetrics((string) $client->id);

            return view('dashboard', [
                'adminMode'        => false,
                'isBarberMode'     => false,
                'isReceptionMode'  => false,
                'isClientMode'     => true,
                'kpis'             => $data['kpis'],
                'loyalty'          => $data['loyalty'],
                'nextAppointment'  => $data['next_appointment'],
                'chatbotTelemetry' => [],
                'visit_chart'      => $data['visit_chart'],
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
        return \Illuminate\Support\Facades\Cache::remember('dashboard_insights', 600, function () {
            $insights = [];

            // 1. Segmento premium: el servicio más caro concentra poco volumen
            //    pero un ticket muy superior (hallazgo KMeans, Unidad IV).
            $premium = \App\Models\Service::where('activo', true)->orderByDesc('precio')->first();
            $totalCitas = \App\Models\Appointment::count();
            if ($premium && $totalCitas > 0) {
                $premiumCitas = \App\Models\Appointment::where('service_id', (string) $premium->id)->count();
                $avgTicket    = (float) (\App\Models\Appointment::avg('precio_cobrado') ?: 1);
                $insights[] = [
                    'titulo' => 'Segmento premium',
                    'dato'   => sprintf('%.1f%% de las citas', $premiumCitas / $totalCitas * 100),
                    'detalle' => sprintf(
                        '"%s" factura %.1fx el ticket promedio ($%s vs $%s) — candidato a upsell.',
                        $premium->nombre, ((float) $premium->precio) / max($avgTicket, 1),
                        number_format((float) $premium->precio, 0), number_format($avgTicket, 0)
                    ),
                ];
            }

            // 2. Cancelaciones: mes en curso vs mes anterior.
            $iniMes  = now()->startOfMonth();
            $iniPrev = now()->subMonthNoOverflow()->startOfMonth();
            $finPrev = $iniMes->copy()->subSecond();
            $mesTot  = \App\Models\Appointment::where('fecha', '>=', $iniMes)->count();
            $mesCan  = \App\Models\Appointment::where('fecha', '>=', $iniMes)->where('estado', 'cancelada')->count();
            $prevTot = \App\Models\Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->count();
            $prevCan = \App\Models\Appointment::whereBetween('fecha', [$iniPrev, $finPrev])->where('estado', 'cancelada')->count();
            if ($mesTot > 0 && $prevTot > 0) {
                $tasaMes  = $mesCan / $mesTot * 100;
                $tasaPrev = $prevCan / $prevTot * 100;
                $insights[] = [
                    'titulo' => 'Cancelaciones del mes',
                    'dato'   => sprintf('%.1f%%', $tasaMes),
                    'detalle' => sprintf(
                        '%s vs %.1f%% el mes pasado (%d de %d citas).',
                        $tasaMes > $tasaPrev ? 'Subió' : 'Bajó', $tasaPrev, $mesCan, $mesTot
                    ),
                ];
            }

            // 3. Hora pico de los últimos 30 días (hallazgo de demanda, Unidad III).
            $horas = \App\Models\Appointment::where('fecha', '>=', now()->subDays(30))
                ->pluck('hora_inicio')
                ->map(fn ($h) => substr((string) $h, 0, 2))
                ->countBy();
            if ($horas->isNotEmpty()) {
                $pico = $horas->sortDesc()->keys()->first();
                $insights[] = [
                    'titulo' => 'Hora pico (30 días)',
                    'dato'   => "{$pico}:00",
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
