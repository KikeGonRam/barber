<?php

namespace App\Services\Dashboard;

use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\RaffleResult;
use App\Models\Service;
use App\Services\Loyalty\LoyaltyService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use MongoDB\BSON\UTCDateTime;

/**
 * Orquesta las métricas y gráficos de los dashboards por rol (admin, barbero,
 * recepcionista, cliente). Agrega datos de citas, pagos, inventario, lealtad
 * y telemetría del chatbot; cachea los resultados pesados para no recalcular
 * en cada carga de página.
 */
class DashboardService
{
    // Suma monto + propina de una query de Payment sin resolver (trae solo las columnas necesarias).
    private function sumPayments($query): float
    {
        return $query->get(['monto', 'propina'])
            ->sum(fn ($p) => (float) ($p->monto ?? 0) + (float) ($p->propina ?? 0));
    }

    // Igual que sumPayments pero sobre una Collection ya cargada (evita volver a golpear la DB).
    private function sumPaymentCollection(Collection $payments): float
    {
        return $payments->sum(fn ($p) => (float) ($p->monto ?? 0) + (float) ($p->propina ?? 0));
    }

    /**
     * Cuenta citas agrupadas por un campo, calculado en el servidor de Mongo
     * (aggregation pipeline) en vez de traer todos los documentos a PHP.
     * Necesario para ventanas grandes (p.ej. "último año") sobre ~100k+ citas,
     * donde un ->get() completo puede agotar el timeout del socket hacia Atlas.
     * Appointment::raw() salta el global scope de SoftDeletes, así que el
     * $match excluye deleted_at a mano; si no, citas borradas (soft-delete)
     * siguen contando aquí, inflando totales como retention_rate por encima
     * de 100%.
     */
    private function aggregateCountsBy(string $groupField, Carbon $since): Collection
    {
        $sinceUtc = new UTCDateTime($since->getTimestamp() * 1000);

        $rows = Appointment::raw(function ($collection) use ($groupField, $sinceUtc) {
            return $collection->aggregate([
                ['$match' => ['fecha' => ['$gte' => $sinceUtc], 'deleted_at' => null]],
                ['$group' => ['_id' => '$'.$groupField, 'total' => ['$sum' => 1]]],
            ]);
        });

        return collect($rows)->mapWithKeys(fn ($row) => [(string) $row->id => (int) $row->total]);
    }

    /**
     * KPIs y gráficos del dashboard de administrador. Efecto secundario: cachea 120s (clave global,
     * no depende de usuario) para evitar recalcular las agregaciones pesadas en cada request.
     */
    public function adminMetrics(): array
    {
        return Cache::remember('dashboard.admin.metrics', 120, fn () => $this->buildAdminMetrics());
    }

    private function buildAdminMetrics(): array
    {
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = (clone $monthStart)->subMonth();
        $lastMonthEnd = (clone $monthEnd)->subMonth();

        // --- Appointment counts (4 indexed queries, fast) ---
        $appointmentsToday = Appointment::whereDate('fecha', $today)->count();
        $appointmentsWeek = Appointment::whereBetween('fecha', [$weekStart, $weekEnd])->count();
        $appointmentsMonth = Appointment::whereBetween('fecha', [$monthStart, $monthEnd])->count();
        $appointmentsLastMonth = Appointment::whereBetween('fecha', [$lastMonthStart, $lastMonthEnd])->count();

        $appointmentGrowth = $appointmentsLastMonth > 0
            ? (($appointmentsMonth - $appointmentsLastMonth) / $appointmentsLastMonth) * 100 : 0;

        // --- Income KPIs — 1 query for the full range, grouped in PHP ---
        $allPayments = Payment::where('created_at', '>=', $lastMonthStart->copy()->startOfDay())
            ->get(['created_at', 'monto', 'propina']);

        $incomeToday = $this->sumPaymentCollection($allPayments->filter(fn ($p) => Carbon::parse($p->created_at)->isToday()));
        $incomeWeek = $this->sumPaymentCollection($allPayments->filter(fn ($p) => Carbon::parse($p->created_at)->between($weekStart, $weekEnd)));
        $incomeMonth = $this->sumPaymentCollection($allPayments->filter(fn ($p) => Carbon::parse($p->created_at)->between($monthStart, $monthEnd)));
        $incomeLastMonth = $this->sumPaymentCollection($allPayments->filter(fn ($p) => Carbon::parse($p->created_at)->between($lastMonthStart, $lastMonthEnd)));

        $incomeGrowth = $incomeLastMonth > 0
            ? (($incomeMonth - $incomeLastMonth) / $incomeLastMonth) * 100 : 0;

        // --- Top barber this month ---
        $barberGroups = Appointment::whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map(fn ($g) => $g->count())
            ->sortDesc();
        $topBarberId = $barberGroups->keys()->first();
        $topBarber = $topBarberId ? Barber::with('user')->find($topBarberId) : null;
        $topBarberTotal = $barberGroups->first() ?? 0;

        // --- Top services — date-limited to last year, batch Service lookup ---
        $yearAgo = Carbon::now()->subYear();
        $serviceGroups = $this->aggregateCountsBy('service_id', $yearAgo)->sortDesc()->take(5);
        $serviceIds = $serviceGroups->keys()->filter()->all();
        $servicesMap = Service::find($serviceIds)->keyBy(fn ($s) => (string) $s->id);
        $topServices = $serviceGroups->map(function ($total, $serviceId) use ($servicesMap) {
            return (object) [
                'total' => $total,
                'service' => $servicesMap->get((string) $serviceId),
            ];
        })->values();

        // --- Client stats — date-limited to last year ---
        $clientGroups = $this->aggregateCountsBy('client_id', $yearAgo);
        $newClients = $clientGroups->filter(fn ($t) => $t === 1)->count();
        $recurringClients = $clientGroups->filter(fn ($t) => $t > 1)->count();
        $totalClients = Client::count();
        $recentClientIds = Appointment::where('fecha', '>=', Carbon::now()->subDays(30))
            ->get(['client_id'])
            ->pluck('client_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $activeClients = $recentClientIds->count();
        $retentionRate = $totalClients > 0 ? ($recurringClients / $totalClients) * 100 : 0;

        $lowStockCount = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

        // --- Barbers status — 1 batch query instead of N+1 ---
        $barbers = Barber::with('user')->where('activo', true)->get();
        $now = Carbon::now();
        $barberIds = $barbers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $currentTime = $now->format('H:i:s');

        $currentAppointments = Appointment::whereIn('barber_id', $barberIds)
            ->whereDate('fecha', $now->toDateString())
            ->where('hora_inicio', '<=', $currentTime)
            ->where('hora_fin', '>=', $currentTime)
            ->where('estado', '!=', 'cancelada')
            ->get()
            ->keyBy('barber_id');

        $barbersStatus = $barbers->map(function ($barber) use ($now, $currentAppointments) {
            $currentAppt = $currentAppointments->get((string) $barber->id);
            $isBusy = (bool) $currentAppt;
            $progress = 0;
            if ($isBusy) {
                $start = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_inicio);
                $end = Carbon::parse($currentAppt->fecha)->setTimeFromTimeString($currentAppt->hora_fin);
                $total = $end->diffInMinutes($start);
                $elapsed = $now->diffInMinutes($start);
                $progress = min(100, max(0, round(($elapsed / ($total ?: 1)) * 100)));
            }

            return ['name' => $barber->user?->name ?? 'Barbero', 'is_busy' => $isBusy, 'progress' => $progress];
        });

        // --- Income by week — 1 query for 8-week range, grouped in PHP ---
        $weekRangeStart = Carbon::now()->startOfWeek()->subWeeks(7);
        $weekRangeEnd = Carbon::now()->endOfWeek();
        $weeklyPayments = Payment::whereBetween('created_at', [$weekRangeStart, $weekRangeEnd])
            ->get(['created_at', 'monto', 'propina']);

        $incomeByWeek = collect(range(0, 7))->map(function (int $offset) use ($weeklyPayments) {
            $start = Carbon::now()->startOfWeek()->subWeeks(7 - $offset);
            $end = (clone $start)->endOfWeek();
            $total = $this->sumPaymentCollection(
                $weeklyPayments->filter(fn ($p) => Carbon::parse($p->created_at)->between($start, $end))
            );

            return ['label' => $start->format('d M'), 'total' => $total];
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
                'top_barber_name' => $topBarber?->user?->name,
                'top_barber_total' => $topBarberTotal,
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

    // Ranking de los 8 barberos con más citas completadas del mes, con ingresos por barbero.
    private function getBarberPerformanceChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        $appointments = Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['barber_id', 'precio_cobrado']);

        $barberStats = $appointments->groupBy('barber_id')
            ->map(fn ($g, $id) => [
                'barber_id' => $id,
                'appointments' => $g->count(),
                'revenue' => $g->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0)),
            ])
            ->sortByDesc('appointments')
            ->take(8)
            ->values();

        $barberIds = $barberStats->pluck('barber_id')->filter()->all();
        $barbers = Barber::with('user')->find($barberIds)->keyBy(fn ($b) => (string) $b->id);

        return [
            'labels' => $barberStats->map(fn ($row) => $barbers->get((string) $row['barber_id'])?->user?->name ?? 'Sin nombre')->all(),
            'appointments' => $barberStats->pluck('appointments')->all(),
            'revenue' => $barberStats->pluck('revenue')->map(fn ($v) => (float) ($v ?? 0))->all(),
        ];
    }

    // Tendencia de citas completadas del mes, agrupada en 12 buckets de 3 días.
    private function getClientTrendsChart(Carbon $monthStart, Carbon $monthEnd): array
    {
        // 1 query for the whole month, grouped in PHP (was 12 queries)
        $apptDates = Appointment::where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['fecha'])
            ->map(fn ($a) => substr((string) $a->fecha, 0, 10));

        $clientTrends = collect(range(0, 11))->map(function (int $offset) use ($monthStart, $apptDates) {
            $date = (clone $monthStart)->addDays($offset * 3);
            $dateEnd = (clone $date)->addDays(3);
            $ds = $date->toDateString();
            $de = $dateEnd->toDateString();
            $count = $apptDates->filter(fn ($d) => $d >= $ds && $d < $de)->count();

            return ['label' => $date->format('d M'), 'count' => $count];
        });

        return [
            'labels' => $clientTrends->pluck('label')->all(),
            'values' => $clientTrends->pluck('count')->all(),
        ];
    }

    /**
     * KPIs y gráficos del dashboard del barbero. Efecto secundario: cachea 60s por barberId.
     */
    public function barberMetrics(string $barberId): array
    {
        return Cache::remember("dashboard.barber.{$barberId}", 60, fn () => $this->buildBarberMetrics($barberId));
    }

    private function buildBarberMetrics(string $barberId): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $appointmentsToday = Appointment::where('barber_id', $barberId)
            ->whereDate('fecha', $today)
            ->count();

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->count();

        $incomeMonth = (float) Appointment::where('barber_id', $barberId)
            ->where('estado', 'completada')
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->sum('precio_cobrado');

        // Propinas del mes: suma de propina en pagos de citas de este barbero.
        $monthApptIds = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
        $tipsMonth = empty($monthApptIds)
            ? 0.0
            : (float) Payment::whereIn('appointment_id', $monthApptIds)->get(['propina'])->sum(fn ($p) => (float) $p->propina);

        // Top services — date-limited to last year, batch Service lookup
        $yearAgo = Carbon::now()->subYear();
        $svcGroups = Appointment::where('barber_id', $barberId)
            ->where('fecha', '>=', $yearAgo)
            ->get(['service_id'])
            ->groupBy('service_id')
            ->map(fn ($g) => $g->count())
            ->sortDesc()
            ->take(5);
        $svcIds = $svcGroups->keys()->filter()->all();
        $svcMap = Service::find($svcIds)->keyBy(fn ($s) => (string) $s->id);
        $topServices = $svcGroups->map(function ($total, $serviceId) use ($svcMap) {
            return (object) ['total' => $total, 'service' => $svcMap->get((string) $serviceId)];
        })->values();

        // Performance by day — 1 query for last 7 days, grouped in PHP (was 7 queries)
        $weekStart = Carbon::now()->subDays(6)->startOfDay();
        $weekEnd = Carbon::now()->endOfDay();
        $weeklyAppts = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$weekStart, $weekEnd])
            ->get(['fecha'])
            ->map(fn ($a) => substr((string) $a->fecha, 0, 10));

        $performanceByDay = collect(range(0, 6))->map(function (int $offset) use ($weeklyAppts) {
            $date = Carbon::now()->subDays(6 - $offset)->toDateString();
            $count = $weeklyAppts->filter(fn ($d) => $d === $date)->count();

            return ['label' => Carbon::parse($date)->format('D'), 'total' => $count];
        });

        return [
            'kpis' => [
                'appointments_today' => $appointmentsToday,
                'appointments_month' => $appointmentsMonth,
                'income_month' => $incomeMonth,
                'tips_month' => $tipsMonth,
                'rating' => 4.9,
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

    /**
     * KPIs y gráficos del dashboard de recepción. Efecto secundario: cachea 60s (clave global).
     */
    public function receptionistMetrics(): array
    {
        return Cache::remember('dashboard.receptionist.metrics', 60, fn () => $this->buildReceptionistMetrics());
    }

    private function buildReceptionistMetrics(): array
    {
        $today = Carbon::today();

        $appointmentsToday = Appointment::whereDate('fecha', $today)->count();
        $pendingPayments = Appointment::whereDate('fecha', $today)
            ->where('estado', 'completada')
            ->whereDoesntHave('payments')
            ->count();
        $newClientsToday = Client::whereDate('created_at', $today)->count();

        $lowStockCount = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

        // Pedidos de tienda por entregar (bandeja de recepción).
        $pendingOrders = Order::where('estado', 'pendiente')->count();
        $pendingOrdersList = Order::with('client.user')
            ->where('estado', 'pendiente')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Cobrado hoy = pagos de servicios + pedidos de tienda entregados hoy.
        $collectedServices = (float) Payment::whereDate('created_at', $today)
            ->get(['monto', 'propina'])
            ->sum(fn ($p) => (float) $p->monto + (float) $p->propina);
        $collectedOrders = (float) Order::where('estado', 'entregado')
            ->whereDate('entregado_en', $today)
            ->get(['total'])
            ->sum(fn ($o) => (float) $o->total);
        $collectedToday = $collectedServices + $collectedOrders;

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
                'pending_payments' => $pendingPayments,
                'new_clients_today' => $newClientsToday,
                'low_stock_count' => $lowStockCount,
                'pending_orders' => $pendingOrders,
                'collected_today' => $collectedToday,
            ],
            'next_appointments' => $nextAppointments,
            'pending_orders_list' => $pendingOrdersList,
            'flow_chart' => $this->getReceptionistFlowData(),
        ];
    }

    /**
     * KPIs, lealtad y gráficos del dashboard del cliente. A diferencia de los otros
     * dashboards, NO usa Cache::remember (se recalcula en cada request).
     */
    public function clientMetrics(string $clientId): array
    {
        $client = Client::find($clientId);

        $appointmentStates = Appointment::where('client_id', $clientId)->get(['estado']);
        $totalAppointments = $appointmentStates->count();
        // OJO: "?? " no basta aquí. El cast 'integer' de Client::total_citas
        // convierte un campo ausente en el documento (cliente nunca migrado a
        // este contador) en 0, no en null — así que "$client?->total_citas ?? X"
        // nunca cae a X mientras $client exista, incluso si el contador nunca
        // se sembró. Se revisa el atributo crudo para distinguir "ausente" de
        // "presente y en cero" y sí recontar en vivo en el primer caso.
        $completedAppointments = ($client && array_key_exists('total_citas', $client->getAttributes()))
            ? (int) $client->total_citas
            : $appointmentStates->where('estado', 'completada')->count();
        $canceledAppointments = $appointmentStates->where('estado', 'cancelada')->count();
        $completedForRate = min($completedAppointments, $totalAppointments);
        $completionRate = $totalAppointments > 0 ? ($completedForRate / $totalAppointments) * 100 : 0;
        $cancellationRate = $totalAppointments > 0 ? ($canceledAppointments / $totalAppointments) * 100 : 0;

        $nextAppt = Appointment::with(['barber.user', 'service'])
            ->where('client_id', $clientId)
            ->where('fecha', '>=', Carbon::today())
            ->whereNotIn('estado', ['cancelada', 'completada', 'no_asistio'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->first();

        $favoriteBarberData = Appointment::where('client_id', $clientId)
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map(fn ($g) => $g->count())
            ->sortDesc();
        $favoriteBarberId = $favoriteBarberData->keys()->first();
        $favoriteBarber = $favoriteBarberId ? Barber::with('user')->find($favoriteBarberId) : null;

        // Datos de lealtad desde el modelo (campo persistido)
        $nivel = $client?->nivel ?? LoyaltyService::nivelFromCitas($completedAppointments);
        $puntos = $client?->puntos ?? 0;
        $discount = LoyaltyService::discountPct($nivel);

        $nextNivel = LoyaltyService::nextLevel($nivel);
        $citasNextNivel = $nextNivel ? LoyaltyService::citasForLevel($nextNivel) : null;
        $citasFaltan = $citasNextNivel ? max(0, $citasNextNivel - $completedAppointments) : 0;

        // Últimas transacciones de puntos
        $recentTransactions = LoyaltyTransaction::where('client_id', $clientId)
            ->latest()
            ->limit(5)
            ->get();

        // Ganador último sorteo
        $lastRaffle = RaffleResult::where('client_id', $clientId)
            ->latest()
            ->first();

        // 1 query for 6-month range, grouped by month in PHP (replaces 6 slow whereMonth queries)
        $sixMonthStart = Carbon::now()->subMonths(5)->startOfMonth();
        $clientVisitDates = Appointment::where('client_id', $clientId)
            ->where('estado', 'completada')
            ->where('fecha', '>=', $sixMonthStart)
            ->pluck('fecha')
            ->map(fn ($f) => substr((string) $f, 0, 7)); // "YYYY-MM"

        $visitData = collect(range(0, 5))->map(function ($offset) use ($clientVisitDates) {
            $date = Carbon::now()->subMonths(5 - $offset);
            $key = $date->format('Y-m');
            $count = $clientVisitDates->filter(fn ($d) => $d === $key)->count();

            return ['label' => $date->translatedFormat('M'), 'total' => $count];
        });

        return [
            'kpis' => [
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'completion_rate' => round($completionRate, 1),
                'cancellation_rate' => round($cancellationRate, 1),
                'favorite_barber' => $favoriteBarber?->user?->name ?? 'Por descubrir',
                'membership_status' => LoyaltyService::LEVEL_LABELS[$nivel] ?? strtoupper($nivel),
            ],
            'loyalty' => [
                'nivel' => $nivel,
                'puntos' => $puntos,
                'discount_pct' => $discount,
                'next_nivel' => $nextNivel,
                'citas_faltan' => $citasFaltan,
                'citas_next_nivel' => $citasNextNivel,
                'progress_pct' => $citasNextNivel ? min(100, ($completedAppointments / $citasNextNivel) * 100) : 100,
                'recent_transactions' => $recentTransactions,
                'won_raffle' => $lastRaffle,
            ],
            'next_appointment' => $nextAppt ? [
                'id' => $nextAppt->id,
                'code' => $nextAppt->code,
                'fecha' => optional($nextAppt->fecha)->toDateString(),
                'hora_inicio' => $nextAppt->hora_inicio,
                'hora_fin' => $nextAppt->hora_fin,
                'estado' => $nextAppt->estado,
                'service' => $nextAppt->service ? ['id' => $nextAppt->service->id, 'nombre' => $nextAppt->service->nombre, 'precio' => $nextAppt->service->precio] : null,
                'barber' => $nextAppt->barber ? [
                    'id' => $nextAppt->barber->id,
                    'slug' => $nextAppt->barber->slug,
                    'user' => $nextAppt->barber->user ? ['name' => $nextAppt->barber->user->name] : null,
                ] : null,
            ] : null,
            'visit_chart' => [
                'labels' => $visitData->pluck('label')->all(),
                'values' => $visitData->pluck('total')->all(),
            ],
        ];
    }

    // Distribución de citas del día por hora, para el gráfico de flujo de recepción.
    private function getReceptionistFlowData(): array
    {
        $hours = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00'];

        // 1 query for the whole day, grouped in PHP (was 12 queries)
        $startTimes = Appointment::whereDate('fecha', Carbon::today())
            ->get(['hora_inicio'])
            ->map(fn ($a) => substr((string) $a->hora_inicio, 0, 5));

        $counts = array_map(function (string $hour) use ($startTimes) {
            $nextHour = Carbon::parse($hour)->addHour()->format('H:i');

            return $startTimes->filter(fn ($h) => $h >= $hour && $h < $nextHour)->count();
        }, $hours);

        return ['labels' => $hours, 'values' => $counts];
    }

    // Resumen de telemetría del chatbot (volumen, tasa de error, latencia, costo estimado)
    // leído del log de Activity (spatie/laravel-activitylog) filtrado por evento chatbot_provider_telemetry.
    private function chatbotTelemetrySummary(int $days): array
    {
        $start = Carbon::now()->subDays(max(0, $days - 1))->startOfDay();
        // Load created_at too so we can reuse events for the trend chart (no extra queries)
        $telemetryEvents = Activity::where('log_name', 'chatbot')
            ->where('description', 'chatbot_provider_telemetry')
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'properties']);

        $total = $errors = $latencyTotal = 0;
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
            $bySource[$source] = ($bySource[$source] ?? 0) + 1;
        }

        arsort($bySource);

        return [
            'window_days' => $days,
            'total_requests' => $total,
            'errors' => $errors,
            'error_rate_pct' => $total > 0 ? round(($errors / $total) * 100, 2) : 0.0,
            'avg_latency_ms' => $total > 0 ? (int) round($latencyTotal / $total) : 0,
            'estimated_cost_usd' => round($costTotal, 6),
            'top_sources' => collect($bySource)->take(4)->all(),
            // Pass already-loaded events to avoid 7 redundant queries
            'trend_chart' => $this->chatbotTelemetryTrend($days, $telemetryEvents),
        ];
    }

    // Serie diaria de tasa de error y latencia del chatbot, a partir de eventos ya cargados por chatbotTelemetrySummary.
    private function chatbotTelemetryTrend(int $days, Collection $events): array
    {
        // Group events by date in PHP — no extra DB queries (was 7 queries)
        $byDate = $events->groupBy(fn ($e) => Carbon::parse($e->created_at)->toDateString());

        $data = collect(range(0, $days - 1))->map(function (int $offset) use ($days, $byDate) {
            $date = Carbon::now()->subDays($days - 1 - $offset)->toDateString();
            $dayEvents = $byDate->get($date, collect());
            $count = $dayEvents->count();
            $errors = 0;
            $latencyTotal = 0;

            foreach ($dayEvents as $event) {
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
