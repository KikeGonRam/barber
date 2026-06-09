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
            ]);
        }

        if ($user->hasRole('barbero') && $user->barberProfile) {
            $data = $this->dashboardService->barberMetrics((string) $user->barberProfile->id);

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => true,
                'isReceptionMode' => false,
                'isClientMode' => false,
                'kpis' => $data['kpis'],
                'performanceChart' => $data['performance_chart'],
                'servicesChart' => $data['services_chart'],
                'chatbotTelemetry' => [],
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
                'adminMode' => false,
                'isBarberMode' => false,
                'isReceptionMode' => false,
                'isClientMode' => true,
                'kpis' => $data['kpis'],
                'nextAppointment' => $data['next_appointment'],
                'chatbotTelemetry' => [],
                'visit_chart' => $data['visit_chart'],
            ]);
        }

        return view('dashboard', [
            'adminMode' => false,
            'isBarberMode' => false,
            'isReceptionMode' => false,
            'isClientMode' => false,
        ]);
    }
}
