<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function index(): View
    {
        $user = request()->user();

        if (! $user || ! $user->hasRole('administrador')) {
            return view('dashboard', [
                'adminMode' => false,
            ]);
        }

        $data = $this->dashboardService->adminMetrics();

        return view('dashboard', [
            'adminMode' => true,
            'kpis' => $data['kpis'],
            'incomeChart' => $data['income_chart'],
            'servicesChart' => $data['services_chart'],
        ]);
    }
}
