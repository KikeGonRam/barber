<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\BarbershopSetting;
use App\Services\DashboardService;
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
            $setting = BarbershopSetting::firstOrCreate(
                ['id' => 1],
                ['nombre' => config('app.name'), 'politica_cancelacion' => 24]
            );

            return view('dashboard', [
                'adminMode' => true,
                'isBarberMode' => false,
                'isReceptionMode' => false,
                'isClientMode' => false,
                'kpis' => $data['kpis'],
                'incomeChart' => $data['income_chart'],
                'servicesChart' => $data['services_chart'],
                'maintenanceMode' => $setting?->maintenance_mode ?? false,
            ]);
        }

        if ($user->hasRole('barbero') && $user->barberProfile) {
            $data = $this->dashboardService->barberMetrics($user->barberProfile->id);

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => true,
                'isReceptionMode' => false,
                'kpis' => $data['kpis'],
                'performanceChart' => $data['performance_chart'],
                'servicesChart' => $data['services_chart'],
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
            ]);
        }

        if ($user->hasRole('cliente')) {
            $client = $user->clientProfile;
            if (! $client) {
                $client = $user->clientProfile()->create();
            }

            $data = $this->dashboardService->clientMetrics($client->id);

            return view('dashboard', [
                'adminMode' => false,
                'isBarberMode' => false,
                'isReceptionMode' => false,
                'isClientMode' => true,
                'kpis' => $data['kpis'],
                'nextAppointment' => $data['next_appointment'],
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
