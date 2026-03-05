<?php

namespace App\Services;

use App\Models\Appointment;
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
}
