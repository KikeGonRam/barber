<?php

namespace App\Http\Controllers\Api\Admin\Report;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\Analytics\BarberCommissionService;
use App\Services\Analytics\PeriodComparisonService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de reportes del panel admin.
 * Genera reportes de ingresos, citas, inventario y clientes agregados por
 * periodo. (generateCustomReport/exportReport/listReports existieron como
 * placeholders sin consumidor real -- confirmado con grep sobre
 * frontend-urban -- y devolvían éxito fabricado sin generar nada de
 * verdad; eliminados el 2026-09-10 en vez de dejarlos como una trampa
 * para un futuro cliente Android nativo, ver guardrail #11.)
 */
class ReportAdminController
{
    // Defensa en profundidad: aunque la ruta ya exige
    // role.custom:administrador,ingeniero, este guard evita que un descuido
    // en routes/api.php exponga reportes financieros. ingeniero es un rol de
    // solo lectura (puede consultar/exportar reportes, nunca gestionarlos).
    private function authorizeAdmin(): void
    {
        $user = request()->user();
        abort_if(! $user || (! $user->hasRole('administrador') && ! $user->hasRole('ingeniero')), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    // Ingresos totales/diarios/por barbero dentro del periodo (dia/semana/mes/trimestre/año)
    public function generateRevenueReport(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $period = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $appointments = Appointment::whereBetween('fecha', [$startDate, $endDate])
            ->where('estado', 'completada')
            ->get(['fecha', 'barber_id', 'precio_cobrado', 'service_id']);

        // O(N) single pass instead of O(N*D) while-loop
        $dailyRevenue = [];
        foreach ($appointments as $a) {
            $key = is_string($a->fecha) ? substr($a->fecha, 0, 10) : optional($a->fecha)->format('Y-m-d');
            if ($key) {
                $dailyRevenue[$key] = ($dailyRevenue[$key] ?? 0) + (float) ($a->precio_cobrado ?? 0);
            }
        }
        ksort($dailyRevenue);

        // Batch-load barber names (1 query instead of eager loading N relationships)
        $barberIds = $appointments->pluck('barber_id')->filter()->unique()->values()->all();
        $barbersMap = Barber::with('user:id,name')
            ->find($barberIds)
            ->keyBy(fn ($b) => (string) $b->id);

        $revenueByBarber = $appointments->groupBy('barber_id')->map(function ($g, $barberId) use ($barbersMap) {
            $total = (float) $g->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

            return [
                'barber' => $barbersMap->get((string) $barberId)?->user?->name ?? 'Sin barbero',
                'total' => $total,
                'count' => $g->count(),
                'average' => $g->count() > 0 ? round($total / $g->count(), 2) : 0,
            ];
        });

        $totalRevenue = (float) $appointments->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'totalRevenue' => $totalRevenue,
                'totalAppointments' => $appointments->count(),
                'averageRevenue' => $appointments->count() > 0
                    ? round($totalRevenue / $appointments->count(), 2)
                    : 0,
                'dailyRevenue' => $dailyRevenue,
                'revenueByBarber' => $revenueByBarber,
            ],
        ]);
    }

    // Desglose de citas por estado y por barbero dentro del periodo, con tasa de ocupación/finalización
    public function generateAppointmentsReport(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $period = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $appointments = Appointment::whereBetween('fecha', [$startDate, $endDate])
            ->get(['fecha', 'barber_id', 'estado']);

        $byStatus = [
            'completada' => $appointments->where('estado', 'completada')->count(),
            'pendiente' => $appointments->where('estado', 'pendiente')->count(),
            'cancelada' => $appointments->where('estado', 'cancelada')->count(),
            'no_asistio' => $appointments->where('estado', 'no_asistio')->count(),
        ];

        // Batch-load barber names (1 query instead of eager loading N relationships)
        $barberIds = $appointments->pluck('barber_id')->filter()->unique()->values()->all();
        $barbersMap = Barber::with('user:id,name')
            ->find($barberIds)
            ->keyBy(fn ($b) => (string) $b->id);

        $byBarber = $appointments->groupBy('barber_id')->map(function ($g, $barberId) use ($barbersMap) {
            $completadas = $g->where('estado', 'completada')->count();

            return [
                'barber' => $barbersMap->get((string) $barberId)?->user?->name ?? 'Sin barbero',
                'total' => $g->count(),
                'completadas' => $completadas,
                'canceladas' => $g->where('estado', 'cancelada')->count(),
                'occupancyRate' => round(($completadas / max($g->count(), 1)) * 100),
            ];
        });

        $diffDays = max($startDate->diffInDays($endDate), 1);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'totalAppointments' => $appointments->count(),
                'appointmentsByStatus' => $byStatus,
                'completionRate' => round(($byStatus['completada'] / max($appointments->count(), 1)) * 100),
                'appointmentsByBarber' => $byBarber,
                'averageAppointmentsPerDay' => round($appointments->count() / $diffDays, 1),
            ],
        ]);
    }

    // Estado del inventario: valor total, productos por categoría y movimientos recientes del último mes
    public function generateInventoryReport(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $products = Product::all(['nombre', 'categoria', 'stock_actual', 'stock_minimo', 'precio_venta', 'precio_compra']);
        $movements = InventoryMovement::where('created_at', '>=', Carbon::now()->subMonth())
            ->with('product:id,nombre')
            ->get(['tipo', 'cantidad', 'product_id', 'created_at']);

        $byType = [
            'entrada' => $movements->where('tipo', 'entrada')->count(),
            'salida' => $movements->where('tipo', 'salida')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'totalProducts' => $products->count(),
                'totalValue' => (float) $products->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                'lowStockCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) <= ($p->stock_minimo ?? 0))->count(),
                'criticalCount' => $products->filter(fn ($p) => ($p->stock_actual ?? 0) < (($p->stock_minimo ?? 0) / 2))->count(),
                'productsByCategory' => $products->groupBy('categoria')->map(fn ($g) => [
                    'count' => $g->count(),
                    'totalValue' => (float) $g->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                ]),
                'recentMovements' => $movements->take(10)->map(fn ($m) => [
                    'product' => $m->product?->nombre,
                    'tipo' => $m->tipo,
                    'cantidad' => $m->cantidad,
                    'fecha' => optional($m->created_at)->toIso8601String(),
                ]),
                'movementsByType' => $byType,
            ],
        ]);
    }

    // Conteos de clientes nuevos/activos/inactivos/leales dentro del periodo y retención
    public function generateClientsReport(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $period = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);

        $totalClients = Client::count();
        $newClients = Client::where('created_at', '>=', $startDate)->count();

        $activeClients = Client::whereHas('appointments', function ($q) use ($startDate) {
            $q->where('fecha', '>=', $startDate);
        })->count();

        $inactiveClients = Client::whereDoesntHave('appointments', function ($q) {
            $q->where('fecha', '>=', Carbon::now()->subMonths(3));
        })->count();

        $loyalClients = Client::whereHas('appointments', function ($q) {
            $q->where('estado', 'completada');
        }, '>', 10)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'totalClients' => $totalClients,
                'newClients' => $newClients,
                'activeClients' => $activeClients,
                'inactiveClients' => $inactiveClients,
                'loyaltyClients' => $loyalClients,
                'clientRetention' => round(($activeClients / max($totalClients, 1)) * 100),
            ],
        ]);
    }

    /**
     * Comisiones de barberos: cuánto le corresponde a cada uno por sus
     * citas completadas en el periodo, sobre el precio de lista del
     * servicio (ver BarberCommissionService -- distinto criterio a
     * propósito de generateRevenueReport(), que usa precio_cobrado real).
     * Acepta fecha_desde/fecha_hasta explícitas (periodo de pago real) o,
     * en su ausencia, el mismo bucket "period" que el resto de reportes.
     */
    public function generateBarberCommissionsReport(Request $request, BarberCommissionService $commissions): JsonResponse
    {
        $this->authorizeAdmin();

        if ($request->filled('fecha_desde') && $request->filled('fecha_hasta')) {
            $start = Carbon::parse($request->query('fecha_desde'));
            $end = Carbon::parse($request->query('fecha_hasta'));
        } else {
            $start = $this->getStartDate($request->query('period', 'mes'));
            $end = Carbon::now();
        }

        return response()->json([
            'success' => true,
            'data' => $commissions->reportFor($start, $end),
        ]);
    }

    /**
     * Demanda por día de la semana y hora, calculada con las citas reales (sin IA: el análisis
     * de /predictions/peak-hours pasa por Ollama, es lento y devuelve texto). Cuenta toda cita que
     * reservó o usó un hueco, es decir, todas menos las canceladas.
     *
     * Usa una ventana en días (30, 90, 180 o 365) y no el "period" del resto de reportes: un día o
     * una semana no bastan para distinguir un patrón de una casualidad.
     */
    public function generatePeakHoursReport(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $days = (int) $request->query('days', 90);
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;
        $endDate = Carbon::now()->endOfDay();
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();

        // `fecha` se guarda como medianoche UTC: se consulta y se lee en UTC. Con la zona de México la
        // medianoche cae en el día anterior (los lunes saldrían como domingo) y el primer día de la ventana se perdería.
        $fromUtc = Carbon::parse($startDate->toDateString(), 'UTC')->startOfDay();
        $toUtc = Carbon::parse($endDate->toDateString(), 'UTC')->endOfDay();

        $appointments = Appointment::whereBetween('fecha', [$fromUtc, $toUtc])
            ->where('estado', '!=', 'cancelada')
            ->get(['fecha', 'hora_inicio']);

        $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

        // Cuántas veces cae cada día de la semana dentro de la ventana (para promediar por semana).
        $occurrences = array_fill(1, 7, 0);
        for ($day = $startDate->copy(); $day->lte($endDate); $day->addDay()) {
            $occurrences[$day->dayOfWeekIso]++;
        }

        $counts = []; // [díaISO][hora] => citas
        $total = 0;
        foreach ($appointments as $appointment) {
            if (! $appointment->hora_inicio || ! $appointment->fecha) {
                continue;
            }
            $date = Carbon::parse($appointment->fecha)->setTimezone('UTC');
            $hour = (int) substr((string) $appointment->hora_inicio, 0, 2);
            $counts[$date->dayOfWeekIso][$hour] = ($counts[$date->dayOfWeekIso][$hour] ?? 0) + 1;
            $total++;
        }

        if ($total === 0) {
            return response()->json(['success' => true, 'data' => [
                'days' => $days,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'totalAppointments' => 0,
                'hours' => [],
                'weekdays' => [],
                'matrix' => [],
                'hourlyTotals' => [],
                'peakSlots' => [],
                'quietSlots' => [],
            ]]);
        }

        $allHours = collect($counts)->flatMap(fn (array $byHour) => array_keys($byHour));
        $hours = range($allHours->min(), $allHours->max());

        $matrix = [];
        $weekdays = [];
        $slots = [];
        foreach ($weekdayNames as $iso => $name) {
            $row = [];
            foreach ($hours as $hour) {
                $row[] = $counts[$iso][$hour] ?? 0;
            }
            $dayTotal = array_sum($row);
            $matrix[] = $row;
            $weekdays[] = ['iso' => $iso, 'label' => $name, 'short' => mb_substr($name, 0, 3), 'total' => $dayTotal, 'occurrences' => $occurrences[$iso]];

            foreach ($hours as $i => $hour) {
                $slots[] = [
                    'weekday' => $name,
                    'weekdayIso' => $iso,
                    'hour' => $hour,
                    'label' => sprintf('%s %02d:00', $name, $hour),
                    'total' => $row[$i],
                    'avgPerWeek' => round($row[$i] / max($occurrences[$iso], 1), 2),
                    'dayTotal' => $dayTotal,
                ];
            }
        }

        $hourlyTotals = [];
        foreach ($hours as $i => $hour) {
            $hourlyTotals[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'total' => array_sum(array_column($matrix, $i)),
            ];
        }

        $byDemand = fn (array $a, array $b) => [$b['total'], $a['weekdayIso'], $a['hour']] <=> [$a['total'], $b['weekdayIso'], $b['hour']];
        $peak = collect($slots)->filter(fn (array $s) => $s['total'] > 0)->sort($byDemand)->take(3)->values()->all();
        // Horas tranquilas: solo en días con actividad (un día cerrado no es "una hora tranquila").
        $quiet = collect($slots)->filter(fn (array $s) => $s['dayTotal'] > 0)
            ->sortBy([['total', 'asc'], ['weekdayIso', 'asc'], ['hour', 'asc']])->take(3)->values()->all();

        $strip = fn (array $s) => array_diff_key($s, ['dayTotal' => true, 'weekdayIso' => true]);

        return response()->json([
            'success' => true,
            'data' => [
                'days' => $days,
                'startDate' => $startDate->toDateString(),
                'endDate' => $endDate->toDateString(),
                'totalAppointments' => $total,
                'hours' => $hours,
                'weekdays' => $weekdays,
                'matrix' => $matrix,
                'hourlyTotals' => $hourlyTotals,
                'peakSlots' => array_map($strip, $peak),
                'quietSlots' => array_map($strip, $quiet),
            ],
        ]);
    }

    // Periodo actual contra el mismo tramo del periodo anterior, con la variación de cada métrica.
    public function generateComparisonReport(Request $request, PeriodComparisonService $comparison): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['data' => $comparison->compare((string) $request->query('period', 'mes'))]);
    }

    // Cohortes de retención: de los clientes que se registraron cada mes, cuántos regresan en los meses siguientes.
    public function generateCohortsReport(Request $request, PeriodComparisonService $comparison): JsonResponse
    {
        $this->authorizeAdmin();

        return response()->json(['data' => $comparison->cohorts((int) $request->query('months', 6))]);
    }

    // Traduce el nombre del periodo a la fecha de inicio correspondiente (mes por defecto)
    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'dia' => Carbon::now()->startOfDay(),
            'semana' => Carbon::now()->startOfWeek(),
            'trimestre' => Carbon::now()->startOfQuarter(),
            'año' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
    }
}
