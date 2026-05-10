<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class DashboardService
{
    public function adminMetrics(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = (clone $monthStart)->subMonth();
        $lastMonthEnd = (clone $monthEnd)->subMonth();

        $appointmentsToday = Appointment::query()->whereDate('fecha', $today)->count();
        $appointmentsWeek = Appointment::query()->whereBetween('fecha', [$weekStart->toDateString(), $weekEnd->toDateString()])->count();
        $appointmentsMonth = Appointment::query()->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])->count();
        $appointmentsLastMonth = Appointment::query()->whereBetween('fecha', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->count();

        $incomeToday = (float) Payment::query()->whereDate('created_at', $today)->sum(DB::raw('monto + propina'));
        $incomeWeek = (float) Payment::query()->whereBetween('created_at', [$weekStart, $weekEnd])->sum(DB::raw('monto + propina'));
        $incomeMonth = (float) Payment::query()->whereBetween('created_at', [$monthStart, $monthEnd])->sum(DB::raw('monto + propina'));
        $incomeLastMonth = (float) Payment::query()->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])->sum(DB::raw('monto + propina'));

        // Growth metrics
        $appointmentGrowth = $appointmentsLastMonth > 0 ? (($appointmentsMonth - $appointmentsLastMonth) / $appointmentsLastMonth) * 100 : 0;
        $incomeGrowth = $incomeLastMonth > 0 ? (($incomeMonth - $incomeLastMonth) / $incomeLastMonth) * 100 : 0;

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
        $totalClients = Client::count();
        $activeClients = Client::whereHas('appointments', function($q) {
            $q->whereDate('fecha', '>=', Carbon::now()->subDays(30)->toDateString());
        })->count();
        $retentionRate = $totalClients > 0 ? ($recurringClients / $totalClients) * 100 : 0;
        
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
                $start = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_inicio);
                $end = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_fin);
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

        $chatbotTelemetry = $this->chatbotTelemetrySummary(7);

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_week' => $appointmentsWeek,
                'appointments_month' => $appointmentsMonth,
                'appointment_growth' => round($appointmentGrowth, 1),
                'income_today' => $incomeToday,
                'income_week' => $incomeWeek,
                'income_month' => $incomeMonth,
                'income_growth' => round($incomeGrowth, 1),
                'top_barber_name' => $topBarber?->barber?->user?->name,
                'top_barber_total' => $topBarber?->total ?? 0,
                'new_clients' => $newClients,
                'recurring_clients' => $recurringClients,
                'total_clients' => $totalClients,
                'active_clients' => $activeClients,
                'retention_rate' => round($retentionRate, 1),
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
            'barber_performance' => $this->getBarberPerformanceChart($monthStart, $monthEnd),
            'client_trends' => $this->getClientTrendsChart($monthStart, $monthEnd),
            'chatbot_telemetry' => $chatbotTelemetry,
        ];
    }

    private function getBarberPerformanceChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        $barberStats = Appointment::query()
            ->selectRaw('barber_id, COUNT(*) as appointments, SUM(precio_cobrado) as revenue')
            ->with('barber.user')
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->groupBy('barber_id')
            ->orderByDesc('appointments')
            ->limit(8)
            ->get();

        return [
            'labels' => $barberStats->map(fn ($row) => $row->barber?->user?->name ?? 'Sin nombre')->all(),
            'appointments' => $barberStats->pluck('appointments')->all(),
            'revenue' => $barberStats->pluck('revenue')->map(fn ($v) => (float) ($v ?? 0))->all(),
        ];
    }

    private function getClientTrendsChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        $clientTrends = collect(range(0, 11))->map(function (int $offset) use ($monthStart) {
            $date = (clone $monthStart)->addDays(($offset * 3));
            $count = Appointment::query()
                ->where('estado', 'completada')
                ->whereDate('fecha', '>=', $date->toDateString())
                ->whereDate('fecha', '<', $date->addDays(3)->toDateString())
                ->count();

            return [
                'label' => $date->format('d M'),
                'count' => $count,
            ];
        });

        return [
            'labels' => $clientTrends->pluck('label')->all(),
            'values' => $clientTrends->pluck('count')->all(),
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

    private function chatbotTelemetrySummary(int $days): array
    {
        $start = Carbon::now()->subDays(max(0, $days - 1))->startOfDay();
        $telemetryEvents = Activity::query()
            ->where('log_name', 'chatbot')
            ->where('description', 'chatbot_provider_telemetry')
            ->where('created_at', '>=', $start)
            ->get(['properties']);

        $total = 0;
        $errors = 0;
        $latencyTotal = 0;
        $costTotal = 0.0;
        $bySource = [];

        foreach ($telemetryEvents as $event) {
            $rawProps = $event->properties;

            if ($rawProps instanceof Collection) {
                $rawProps = $rawProps->toArray();
            } else {
                $rawProps = (array) $rawProps;
            }

            $props = (array) ($rawProps['attributes'] ?? $rawProps);
            $source = (string) ($props['source'] ?? 'unknown');
            $status = (string) ($props['status'] ?? 'unknown');
            $latency = (int) ($props['latency_ms'] ?? 0);
            $cost = (float) ($props['estimated_cost_usd'] ?? 0);

            $total++;
            $latencyTotal += $latency;
            $costTotal += $cost;

            if ($status === 'error') {
                $errors++;
            }

            if (! isset($bySource[$source])) {
                $bySource[$source] = 0;
            }

            $bySource[$source]++;
        }

        arsort($bySource);

        $trendByDay = $this->chatbotTelemetryTrend(7);

        return [
            'window_days' => $days,
            'total_requests' => $total,
            'errors' => $errors,
            'error_rate_pct' => $total > 0 ? round(($errors / $total) * 100, 2) : 0.0,
            'avg_latency_ms' => $total > 0 ? (int) round($latencyTotal / $total) : 0,
            'estimated_cost_usd' => round($costTotal, 6),
            'top_sources' => collect($bySource)->take(4)->all(),
            'trend_chart' => $trendByDay,
        ];
    }

    private function chatbotTelemetryTrend(int $days): array
    {
        $data = collect(range(0, $days - 1))->map(function (int $offset) use ($days) {
            $date = Carbon::now()->subDays($days - 1 - $offset)->toDateString();
            $start = Carbon::parse($date)->startOfDay();
            $end = Carbon::parse($date)->endOfDay();

            $events = Activity::query()
                ->where('log_name', 'chatbot')
                ->where('description', 'chatbot_provider_telemetry')
                ->whereBetween('created_at', [$start, $end])
                ->get(['properties']);

            $count = $events->count();
            $errors = 0;
            $latencyTotal = 0;

            foreach ($events as $event) {
                $rawProps = $event->properties;
                if ($rawProps instanceof Collection) {
                    $rawProps = $rawProps->toArray();
                } else {
                    $rawProps = (array) $rawProps;
                }

                $props = (array) ($rawProps['attributes'] ?? $rawProps);
                if (($props['status'] ?? null) === 'error') {
                    $errors++;
                }
                $latencyTotal += (int) ($props['latency_ms'] ?? 0);
            }

            return [
                'date' => Carbon::parse($date)->format('M d'),
                'error_rate_pct' => $count > 0 ? round(($errors / $count) * 100, 1) : 0.0,
                'avg_latency_ms' => $count > 0 ? (int) round($latencyTotal / $count) : 0,
            ];
        });

        return [
            'labels' => $data->pluck('date')->all(),
            'error_rates' => $data->pluck('error_rate_pct')->all(),
            'latencies' => $data->pluck('avg_latency_ms')->all(),
        ];
    }
}
