<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    private function sumPayments($query): float
    {
        return $query->get(['monto', 'propina'])
            ->sum(fn($p) => (float) ($p->monto ?? 0) + (float) ($p->propina ?? 0));
    }

    public function adminMetrics(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = (clone $monthStart)->subMonth();
        $lastMonthEnd = (clone $monthEnd)->subMonth();

        $appointmentsToday   = Appointment::whereDate('fecha', $today)->count();
        $appointmentsWeek    = Appointment::whereBetween('fecha', [$weekStart->toDateString(), $weekEnd->toDateString()])->count();
        $appointmentsMonth   = Appointment::whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])->count();
        $appointmentsLastMonth = Appointment::whereBetween('fecha', [$lastMonthStart->toDateString(), $lastMonthEnd->toDateString()])->count();

        $incomeToday      = $this->sumPayments(Payment::whereDate('created_at', $today));
        $incomeWeek       = $this->sumPayments(Payment::whereBetween('created_at', [$weekStart, $weekEnd]));
        $incomeMonth      = $this->sumPayments(Payment::whereBetween('created_at', [$monthStart, $monthEnd]));
        $incomeLastMonth  = $this->sumPayments(Payment::whereBetween('created_at', [$lastMonthStart, $lastMonthEnd]));

        $appointmentGrowth = $appointmentsLastMonth > 0
            ? (($appointmentsMonth - $appointmentsLastMonth) / $appointmentsLastMonth) * 100 : 0;
        $incomeGrowth = $incomeLastMonth > 0
            ? (($incomeMonth - $incomeLastMonth) / $incomeLastMonth) * 100 : 0;

        // Top barber: group in PHP (MongoDB doesn't support selectRaw+groupBy)
        $barberGroups = Appointment::whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map(fn($g) => $g->count())
            ->sortDesc();
        $topBarberId    = $barberGroups->keys()->first();
        $topBarber      = $topBarberId ? Barber::with('user')->find($topBarberId) : null;
        $topBarberTotal = $barberGroups->first() ?? 0;

        // Top services: group in PHP
        $serviceGroups = Appointment::get(['service_id'])
            ->groupBy('service_id')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->take(5);
        $topServices = $serviceGroups->map(function ($total, $serviceId) {
            return (object) [
                'total'   => $total,
                'service' => Service::find($serviceId),
            ];
        })->values();

        // Client stats: group in PHP
        $clientGroups   = Appointment::get(['client_id'])->groupBy('client_id')->map(fn($g) => $g->count());
        $newClients     = $clientGroups->filter(fn($t) => $t === 1)->count();
        $recurringClients = $clientGroups->filter(fn($t) => $t > 1)->count();
        $totalClients   = Client::count();
        $activeClients  = Client::whereHas('appointments', function ($q) {
            $q->whereDate('fecha', '>=', Carbon::now()->subDays(30)->toDateString());
        })->count();
        $retentionRate  = $totalClients > 0 ? ($recurringClients / $totalClients) * 100 : 0;

        // Low stock: MongoDB $expr for field-to-field comparison
        $lowStockCount = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

        // Barber status
        $barbers = Barber::with('user')->where('activo', true)->get();
        $now     = Carbon::now();
        $barbersStatus = $barbers->map(function ($barber) use ($now) {
            $currentAppt = Appointment::where('barber_id', $barber->id)
                ->whereDate('fecha', $now->toDateString())
                ->where('hora_inicio', '<=', $now->format('H:i:s'))
                ->where('hora_fin', '>=', $now->format('H:i:s'))
                ->where('estado', '!=', 'cancelada')
                ->first();
            $isBusy   = (bool) $currentAppt;
            $progress = 0;
            if ($isBusy) {
                $start    = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_inicio);
                $end      = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_fin);
                $total    = $end->diffInMinutes($start);
                $elapsed  = $now->diffInMinutes($start);
                $progress = min(100, max(0, round(($elapsed / ($total ?: 1)) * 100)));
            }
            return ['name' => $barber->user?->name ?? 'Barbero', 'is_busy' => $isBusy, 'progress' => $progress];
        });

        // Income by week chart
        $incomeByWeek = collect(range(0, 7))->map(function (int $offset) {
            $start = Carbon::now()->startOfWeek()->subWeeks(7 - $offset);
            $end   = (clone $start)->endOfWeek();
            return [
                'label' => $start->format('d M'),
                'total' => $this->sumPayments(Payment::whereBetween('created_at', [$start, $end])),
            ];
        });

        $chatbotTelemetry = $this->chatbotTelemetrySummary(7);

        return [
            'kpis' => [
                'appointments_today'   => $appointmentsToday,
                'appointments_week'    => $appointmentsWeek,
                'appointments_month'   => $appointmentsMonth,
                'appointment_growth'   => round($appointmentGrowth, 1),
                'income_today'         => $incomeToday,
                'income_week'          => $incomeWeek,
                'income_month'         => $incomeMonth,
                'income_growth'        => round($incomeGrowth, 1),
                'top_barber_name'      => $topBarber?->user?->name,
                'top_barber_total'     => $topBarberTotal,
                'new_clients'          => $newClients,
                'recurring_clients'    => $recurringClients,
                'total_clients'        => $totalClients,
                'active_clients'       => $activeClients,
                'retention_rate'       => round($retentionRate, 1),
                'low_stock_count'      => $lowStockCount,
                'barbers_status'       => $barbersStatus,
            ],
            'income_chart' => [
                'labels' => $incomeByWeek->pluck('label')->all(),
                'values' => $incomeByWeek->pluck('total')->all(),
            ],
            'services_chart' => [
                'labels' => $topServices->map(fn($row) => $row->service?->nombre ?? 'Sin servicio')->all(),
                'values' => $topServices->pluck('total')->all(),
            ],
            'barber_performance' => $this->getBarberPerformanceChart($monthStart, $monthEnd),
            'client_trends'      => $this->getClientTrendsChart($monthStart, $monthEnd),
            'chatbot_telemetry'  => $chatbotTelemetry,
        ];
    }

    private function getBarberPerformanceChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        $appointments = Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get(['barber_id', 'precio_cobrado']);

        $barberStats = $appointments->groupBy('barber_id')
            ->map(fn($g, $id) => [
                'barber_id'    => $id,
                'appointments' => $g->count(),
                'revenue'      => $g->sum(fn($a) => (float) ($a->precio_cobrado ?? 0)),
            ])
            ->sortByDesc('appointments')
            ->take(8)
            ->values();

        $barberIds = $barberStats->pluck('barber_id')->filter()->all();
        $barbers   = Barber::with('user')->find($barberIds)->keyBy(fn($b) => (string) $b->id);

        return [
            'labels'       => $barberStats->map(fn($row) => $barbers->get((string) $row['barber_id'])?->user?->name ?? 'Sin nombre')->all(),
            'appointments' => $barberStats->pluck('appointments')->all(),
            'revenue'      => $barberStats->pluck('revenue')->map(fn($v) => (float) ($v ?? 0))->all(),
        ];
    }

    private function getClientTrendsChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        $clientTrends = collect(range(0, 11))->map(function (int $offset) use ($monthStart) {
            $date  = (clone $monthStart)->addDays($offset * 3);
            $count = Appointment::where('estado', 'completada')
                ->whereDate('fecha', '>=', $date->toDateString())
                ->whereDate('fecha', '<', (clone $date)->addDays(3)->toDateString())
                ->count();
            return ['label' => $date->format('d M'), 'count' => $count];
        });

        return [
            'labels' => $clientTrends->pluck('label')->all(),
            'values' => $clientTrends->pluck('count')->all(),
        ];
    }

    public function barberMetrics(int $barberId): array
    {
        $today      = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();

        $appointmentsToday = Appointment::where('barber_id', $barberId)
            ->whereDate('fecha', $today)
            ->count();

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();

        $incomeMonth = (float) Appointment::where('barber_id', $barberId)
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('precio_cobrado');

        // Top services for this barber
        $serviceGroups = Appointment::where('barber_id', $barberId)
            ->get(['service_id'])
            ->groupBy('service_id')
            ->map(fn($g) => $g->count())
            ->sortDesc()
            ->take(5);
        $topServices = $serviceGroups->map(function ($total, $serviceId) {
            return (object) ['total' => $total, 'service' => Service::find($serviceId)];
        })->values();

        $performanceByDay = collect(range(0, 6))->map(function (int $offset) use ($barberId) {
            $date  = Carbon::now()->subDays(6 - $offset);
            $count = Appointment::where('barber_id', $barberId)
                ->whereDate('fecha', $date->toDateString())
                ->count();
            return ['label' => $date->format('D'), 'total' => $count];
        });

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_month' => $appointmentsMonth,
                'income_month'       => $incomeMonth,
                'rating'             => 4.9,
            ],
            'performance_chart' => [
                'labels' => $performanceByDay->pluck('label')->all(),
                'values' => $performanceByDay->pluck('total')->all(),
            ],
            'services_chart' => [
                'labels' => $topServices->map(fn($row) => $row->service?->nombre ?? 'Sin servicio')->all(),
                'values' => $topServices->pluck('total')->all(),
            ],
        ];
    }

    public function receptionistMetrics(): array
    {
        $today = Carbon::today();

        $appointmentsToday = Appointment::whereDate('fecha', $today)->count();
        $pendingPayments   = Appointment::whereDate('fecha', $today)
            ->where('estado', 'completada')
            ->whereDoesntHave('payments')
            ->count();
        $newClientsToday = Client::whereDate('created_at', $today)->count();

        // Low stock: MongoDB $expr for field-to-field comparison
        $lowStockCount = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

        $nextAppointments = Appointment::with(['client.user', 'barber.user', 'service'])
            ->whereDate('fecha', $today)
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->where('estado', 'pendiente')
            ->orderBy('hora_inicio')
            ->limit(5)
            ->get();

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'pending_payments'   => $pendingPayments,
                'new_clients_today'  => $newClientsToday,
                'low_stock_count'    => $lowStockCount,
            ],
            'next_appointments' => $nextAppointments,
            'flow_chart'        => $this->getReceptionistFlowData(),
        ];
    }

    public function clientMetrics(int $clientId): array
    {
        $totalAppointments     = Appointment::where('client_id', $clientId)->count();
        $completedAppointments = Appointment::where('client_id', $clientId)->where('estado', 'completada')->count();

        $nextAppt = Appointment::with(['barber.user', 'service'])
            ->where('client_id', $clientId)
            ->where('fecha', '>=', now()->toDateString())
            ->where('estado', '!=', 'cancelada')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();

        $favoriteBarberData = Appointment::where('client_id', $clientId)
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map(fn($g) => $g->count())
            ->sortDesc();
        $favoriteBarberId = $favoriteBarberData->keys()->first();
        $favoriteBarber   = $favoriteBarberId ? Barber::with('user')->find($favoriteBarberId) : null;

        $status = 'Caballero';
        if ($completedAppointments >= 10) {
            $status = 'Leyenda';
        } elseif ($completedAppointments >= 5) {
            $status = 'V.I.P';
        }

        $visitData = collect(range(0, 5))->map(function ($offset) use ($clientId) {
            $date  = Carbon::now()->subMonths(5 - $offset);
            $count = Appointment::where('client_id', $clientId)
                ->where('estado', 'completada')
                ->whereMonth('fecha', $date->month)
                ->whereYear('fecha', $date->year)
                ->count();
            return ['label' => $date->translatedFormat('M'), 'total' => $count];
        });

        return [
            'kpis' => [
                'total_appointments'     => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'favorite_barber'        => $favoriteBarber?->user?->name ?? 'Por descubrir',
                'membership_status'      => $status,
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
        $hours  = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];
        $counts = [];

        foreach ($hours as $hour) {
            $counts[] = Appointment::whereDate('fecha', Carbon::today())
                ->where('hora_inicio', '>=', $hour)
                ->where('hora_inicio', '<', Carbon::parse($hour)->addHour()->format('H:i:s'))
                ->count();
        }

        return ['labels' => $hours, 'values' => $counts];
    }

    private function chatbotTelemetrySummary(int $days): array
    {
        $start = Carbon::now()->subDays(max(0, $days - 1))->startOfDay();
        $telemetryEvents = Activity::where('log_name', 'chatbot')
            ->where('description', 'chatbot_provider_telemetry')
            ->where('created_at', '>=', $start)
            ->get(['properties']);

        $total = $errors = $latencyTotal = 0;
        $costTotal = 0.0;
        $bySource  = [];

        foreach ($telemetryEvents as $event) {
            $rawProps = $event->properties;
            if ($rawProps instanceof Collection) {
                $rawProps = $rawProps->toArray();
            } else {
                $rawProps = (array) $rawProps;
            }
            $props  = (array) ($rawProps['attributes'] ?? $rawProps);
            $source = (string) ($props['source'] ?? 'unknown');
            $status = (string) ($props['status'] ?? 'unknown');
            $latency = (int) ($props['latency_ms'] ?? 0);
            $cost    = (float) ($props['estimated_cost_usd'] ?? 0);

            $total++;
            $latencyTotal += $latency;
            $costTotal    += $cost;
            if ($status === 'error') {
                $errors++;
            }
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
        }

        arsort($bySource);

        return [
            'window_days'        => $days,
            'total_requests'     => $total,
            'errors'             => $errors,
            'error_rate_pct'     => $total > 0 ? round(($errors / $total) * 100, 2) : 0.0,
            'avg_latency_ms'     => $total > 0 ? (int) round($latencyTotal / $total) : 0,
            'estimated_cost_usd' => round($costTotal, 6),
            'top_sources'        => collect($bySource)->take(4)->all(),
            'trend_chart'        => $this->chatbotTelemetryTrend(7),
        ];
    }

    private function chatbotTelemetryTrend(int $days): array
    {
        $data = collect(range(0, $days - 1))->map(function (int $offset) use ($days) {
            $date  = Carbon::now()->subDays($days - 1 - $offset)->toDateString();
            $start = Carbon::parse($date)->startOfDay();
            $end   = Carbon::parse($date)->endOfDay();

            $events = Activity::where('log_name', 'chatbot')
                ->where('description', 'chatbot_provider_telemetry')
                ->whereBetween('created_at', [$start, $end])
                ->get(['properties']);

            $count = $events->count();
            $errors = $latencyTotal = 0;

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
                'date'           => Carbon::parse($date)->format('M d'),
                'error_rate_pct' => $count > 0 ? round(($errors / $count) * 100, 1) : 0.0,
                'avg_latency_ms' => $count > 0 ? (int) round($latencyTotal / $count) : 0,
            ];
        });

        return [
            'labels'      => $data->pluck('date')->all(),
            'error_rates' => $data->pluck('error_rate_pct')->all(),
            'latencies'   => $data->pluck('avg_latency_ms')->all(),
        ];
    }
}
