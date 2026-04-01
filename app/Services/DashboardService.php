<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function adminMetrics(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $appointmentsToday = Appointment::query()->whereDate('fecha', $today)->count();
        $appointmentsWeek = Appointment::query()->whereBetween('fecha', [$weekStart->toDateString(), $weekEnd->toDateString()])->count();
        $appointmentsMonth = Appointment::query()->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])->count();

        $incomeToday = (float) Payment::query()->whereDate('created_at', $today)->sum(DB::raw('monto + propina'));
        $incomeWeek = (float) Payment::query()->whereBetween('created_at', [$weekStart, $weekEnd])->sum(DB::raw('monto + propina'));
        $incomeMonth = (float) Payment::query()->whereBetween('created_at', [$monthStart, $monthEnd])->sum(DB::raw('monto + propina'));

        $topBarber = Appointment::query()
            ->selectRaw('barber_id, COUNT(*) as total')
            ->with('barber.user')
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('barber_id')
            ->orderByDesc('total')
            ->first();

        $topServices = Appointment::query()
            ->selectRaw('service_id, COUNT(*) as total')
            ->with('service:id,nombre')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $clientStats = Appointment::query()
            ->selectRaw('client_id, COUNT(*) as total')
            ->groupBy('client_id')
            ->get();

        $newClients = $clientStats->where('total', 1)->count();
        $recurringClients = $clientStats->where('total', '>', 1)->count();

        $lowStockCount = Product::query()->whereColumn('stock_actual', '<=', 'stock_minimo')->count();

        // New: Barber Status Logic
        $barbers = Barber::with('user')->where('activo', true)->get();
        $now = Carbon::now();

        $barbersStatus = $barbers->map(function ($barber) use ($now) {
            $currentAppt = Appointment::query()
                ->where('barber_id', $barber->id)
                ->whereDate('fecha', $now->toDateString())
                ->where('hora_inicio', '<=', $now->format('H:i:s'))
                ->where('hora_fin', '>=', $now->format('H:i:s'))
                ->where('estado', '!=', 'cancelada')
                ->first();

            $isBusy = (bool) $currentAppt;
            $progress = 0;

            if ($isBusy) {
                $start = Carbon::parse($currentAppt->fecha.' '.$currentAppt->hora_inicio);
                $end = Carbon::parse($currentAppt->fecha.' '.$currentAppt->hora_fin);
                $total = $end->diffInMinutes($start);
                $elapsed = $now->diffInMinutes($start);
                $progress = min(100, max(0, round(($elapsed / ($total ?: 1)) * 100)));
            }

            return [
                'name' => $barber->user?->name ?? 'Barbero',
                'is_busy' => $isBusy,
                'progress' => $progress,
            ];
        });

        $incomeByWeek = collect(range(0, 7))->map(function (int $offset) {
            $start = Carbon::now()->startOfWeek()->subWeeks(7 - $offset);
            $end = (clone $start)->endOfWeek();

            return [
                'label' => $start->format('d M'),
                'total' => (float) Payment::query()->whereBetween('created_at', [$start, $end])->sum(DB::raw('monto + propina')),
            ];
        });

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_week' => $appointmentsWeek,
                'appointments_month' => $appointmentsMonth,
                'income_today' => $incomeToday,
                'income_week' => $incomeWeek,
                'income_month' => $incomeMonth,
                'top_barber_name' => $topBarber?->barber?->user?->name,
                'top_barber_total' => $topBarber?->total ?? 0,
                'new_clients' => $newClients,
                'recurring_clients' => $recurringClients,
                'low_stock_count' => $lowStockCount,
                'barbers_status' => $barbersStatus,
            ],
            'income_chart' => [
                'labels' => $incomeByWeek->pluck('label')->all(),
                'values' => $incomeByWeek->pluck('total')->all(),
            ],
            'services_chart' => [
                'labels' => $topServices->map(fn ($row) => $row->service?->nombre ?? 'Sin servicio')->all(),
                'values' => $topServices->pluck('total')->all(),
            ],
        ];
    }

    public function barberMetrics(int $barberId): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $appointmentsToday = Appointment::query()
            ->where('barber_id', $barberId)
            ->whereDate('fecha', $today)
            ->count();

        $appointmentsMonth = Appointment::query()
            ->where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $incomeMonth = (float) Appointment::query()
            ->where('barber_id', $barberId)
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('precio_cobrado');

        $topServices = Appointment::query()
            ->selectRaw('service_id, COUNT(*) as total')
            ->where('barber_id', $barberId)
            ->with('service:id,nombre')
            ->groupBy('service_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $performanceByDay = collect(range(0, 6))->map(function (int $offset) use ($barberId) {
            $date = Carbon::now()->subDays(6 - $offset);
            $count = Appointment::query()
                ->where('barber_id', $barberId)
                ->whereDate('fecha', $date->toDateString())
                ->count();

            return [
                'label' => $date->format('D'),
                'total' => $count,
            ];
        });

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_month' => $appointmentsMonth,
                'income_month' => $incomeMonth,
                'rating' => 4.9, // Mocked rating
            ],
            'performance_chart' => [
                'labels' => $performanceByDay->pluck('label')->all(),
                'values' => $performanceByDay->pluck('total')->all(),
            ],
            'services_chart' => [
                'labels' => $topServices->map(fn ($row) => $row->service?->nombre ?? 'Sin servicio')->all(),
                'values' => $topServices->pluck('total')->all(),
            ],
        ];
    }

    public function receptionistMetrics(): array
    {
        $today = Carbon::today();

        $appointmentsToday = Appointment::query()->whereDate('fecha', $today)->count();
        $pendingPayments = Appointment::query()
            ->whereDate('fecha', $today)
            ->where('estado', 'completada')
            ->whereDoesntHave('payments')
            ->count();

        $newClientsToday = Client::query()->whereDate('created_at', $today)->count();
        $lowStockCount = Product::query()->whereColumn('stock_actual', '<=', 'stock_minimo')->count();

        $nextAppointments = Appointment::query()
            ->with(['client.user', 'barber.user', 'service'])
            ->whereDate('fecha', $today)
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->where('estado', 'pendiente')
            ->orderBy('hora_inicio')
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'pending_payments' => $pendingPayments,
                'new_clients_today' => $newClientsToday,
                'low_stock_count' => $lowStockCount,
            ],
            'next_appointments' => $nextAppointments,
            'flow_chart' => $this->getReceptionistFlowData(),
        ];
    }

    public function clientMetrics(int $clientId): array
    {
        $totalAppointments = Appointment::query()->where('client_id', $clientId)->count();
        $completedAppointments = Appointment::query()->where('client_id', $clientId)->where('estado', 'completada')->count();

        $nextAppt = Appointment::query()
            ->with(['barber.user', 'service'])
            ->where('client_id', $clientId)
            ->where('fecha', '>=', now()->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();

        $favoriteBarber = Appointment::query()
            ->selectRaw('barber_id, COUNT(*) as total')
            ->with('barber.user')
            ->where('client_id', $clientId)
            ->groupBy('barber_id')
            ->orderByDesc('total')
            ->first();

        $status = 'Caballero';
        if ($completedAppointments >= 10) {
            $status = 'Leyenda';
        } elseif ($completedAppointments >= 5) {
            $status = 'V.I.P';
        }

        // Chart: Visits per month (Last 6 months)
        $visitData = collect(range(0, 5))->map(function ($offset) use ($clientId) {
            $date = Carbon::now()->subMonths(5 - $offset);
            $count = Appointment::where('client_id', $clientId)
                ->where('estado', 'completada')
                ->whereMonth('fecha', $date->month)
                ->whereYear('fecha', $date->year)
                ->count();

            return [
                'label' => $date->translatedFormat('M'),
                'total' => $count,
            ];
        });

        return [
            'kpis' => [
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'favorite_barber' => $favoriteBarber?->barber?->user?->name ?? 'Por descubrir',
                'membership_status' => $status,
            ],
            'next_appointment' => $nextAppt,
            'visit_chart' => [
                'labels' => $visitData->pluck('label')->all(),
                'values' => $visitData->pluck('total')->all(),
            ],
        ];
    }

    private function getReceptionistFlowData(): array
    {
        $hours = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
        $counts = [];

        foreach ($hours as $hour) {
            $counts[] = Appointment::whereDate('fecha', Carbon::today())
                ->where('hora_inicio', '>=', $hour)
                ->where('hora_inicio', '<', Carbon::parse($hour)->addHour()->format('H:i:s'))
                ->count();
        }

        return [
            'labels' => $hours,
            'values' => $counts,
        ];
    }
}
