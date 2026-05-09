<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Product;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class ReportAdminController extends Controller
{
    /**
     * Generar reporte de ingresos
     */
    public function generateRevenueReport(Request $request)
    {
        $period = $request->query('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $appointments = Appointment::whereBetween('date', [$startDate, $endDate])
            ->where('status', 'completed')
            ->with('barber')
            ->get();

        $dailyRevenue = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dayRevenue = $appointments
                ->filter(function ($a) use ($currentDate) {
                    return $a->date->format('Y-m-d') === $currentDate->format('Y-m-d');
                })
                ->sum('price');

            $dailyRevenue[$currentDate->format('Y-m-d')] = $dayRevenue;
            $currentDate->addDay();
        }

        $revenueByBarber = $appointments->groupBy('barber_id')->map(function ($group) {
            return [
                'barber' => $group->first()->barber?->name,
                'total' => $group->sum('price'),
                'count' => $group->count(),
                'average' => $group->avg('price'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalRevenue' => $appointments->sum('price'),
                'totalAppointments' => $appointments->count(),
                'averageRevenue' => $appointments->avg('price'),
                'dailyRevenue' => $dailyRevenue,
                'revenueByBarber' => $revenueByBarber,
                'topService' => $this->getTopService($appointments),
            ],
        ]);
    }

    /**
     * Generar reporte de citas
     */
    public function generateAppointmentsReport(Request $request)
    {
        $period = $request->query('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $appointments = Appointment::whereBetween('date', [$startDate, $endDate])->get();

        $appointmentsByStatus = [
            'completed' => $appointments->filter(fn($a) => $a->status === 'completed')->count(),
            'pending' => $appointments->filter(fn($a) => $a->status === 'pending')->count(),
            'cancelled' => $appointments->filter(fn($a) => $a->status === 'cancelled')->count(),
            'no_show' => $appointments->filter(fn($a) => $a->status === 'no_show')->count(),
        ];

        $appointmentsByBarber = $appointments->groupBy('barber_id')->map(function ($group) {
            return [
                'barber' => $group->first()->barber?->name,
                'total' => $group->count(),
                'completed' => $group->filter(fn($a) => $a->status === 'completed')->count(),
                'cancelled' => $group->filter(fn($a) => $a->status === 'cancelled')->count(),
                'occupancyRate' => round(($group->filter(fn($a) => $a->status === 'completed')->count() / max($group->count(), 1)) * 100),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'totalAppointments' => $appointments->count(),
                'appointmentsByStatus' => $appointmentsByStatus,
                'completionRate' => round(($appointmentsByStatus['completed'] / max($appointments->count(), 1)) * 100),
                'appointmentsByBarber' => $appointmentsByBarber,
                'averageAppointmentsPerDay' => round($appointments->count() / $startDate->diffInDays($endDate)),
            ],
        ]);
    }

    /**
     * Generar reporte de inventario
     */
    public function generateInventoryReport(Request $request)
    {
        $products = Product::all();

        $movements = InventoryMovement::where('created_at', '>=', Carbon::now()->subMonth())->get();

        $movementsByType = [
            'entrada' => $movements->filter(fn($m) => $m->type === 'entrada')->count(),
            'salida' => $movements->filter(fn($m) => $m->type === 'salida')->count(),
            'ajuste' => $movements->filter(fn($m) => $m->type === 'ajuste')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'totalProducts' => $products->count(),
                'totalValue' => $products->sum(fn($p) => $p->quantity * $p->price),
                'lowStockCount' => $products->filter(fn($p) => $p->quantity <= $p->min_stock)->count(),
                'criticalCount' => $products->filter(fn($p) => $p->quantity < ($p->min_stock / 2))->count(),
                'productsByCategory' => $products->groupBy('category')->map(function ($group) {
                    return [
                        'count' => $group->count(),
                        'totalValue' => $group->sum(fn($p) => $p->quantity * $p->price),
                    ];
                }),
                'recentMovements' => $movements->take(10)->map(function ($m) {
                    return [
                        'product' => $m->product?->name,
                        'type' => $m->type,
                        'quantity' => $m->quantity,
                        'date' => $m->created_at,
                    ];
                }),
                'movementsByType' => $movementsByType,
            ],
        ]);
    }

    /**
     * Generar reporte de clientes
     */
    public function generateClientsReport(Request $request)
    {
        $period = $request->query('period', 'month');
        $startDate = $this->getStartDate($period);
        $endDate = Carbon::now();

        $clients = User::whereHas('roles', function ($q) {
            $q->where('name', 'cliente');
        })->with('appointments')->get();

        $newClients = $clients->filter(function ($c) {
            return $c->created_at >= $startDate;
        })->count();

        $activeClients = $clients->filter(function ($c) {
            return $c->appointments->filter(function ($a) {
                return $a->date >= $startDate;
            })->count() > 0;
        })->count();

        $inactiveClients = $clients->filter(function ($c) {
            $lastAppointment = $c->appointments->sortByDesc('date')->first();
            return !$lastAppointment || $lastAppointment->date < Carbon::now()->subMonths(3);
        })->count();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'totalClients' => $clients->count(),
                'newClients' => $newClients,
                'activeClients' => $activeClients,
                'inactiveClients' => $inactiveClients,
                'loyaltyClients' => $clients->filter(fn($c) => $c->appointments->count() > 10)->count(),
                'averageAppointmentsPerClient' => round($clients->sum(fn($c) => $c->appointments->count()) / max($clients->count(), 1)),
                'clientRetention' => round(($activeClients / max($clients->count(), 1)) * 100),
            ],
        ]);
    }

    /**
     * Generar reporte personalizado
     */
    public function generateCustomReport(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:ingresos,citas,inventario,clientes,desempeño',
            'period' => 'required|in:dia,semana,mes,trimestre,año,personalizado',
            'dateFrom' => 'nullable|date',
            'dateTo' => 'nullable|date',
            'detail' => 'required|in:resumen,detallado,muy-detallado',
        ]);

        $startDate = $validated['dateFrom'] ? Carbon::parse($validated['dateFrom']) : $this->getStartDate($validated['period']);
        $endDate = $validated['dateTo'] ? Carbon::parse($validated['dateTo']) : Carbon::now();

        $reportData = [
            'id' => uniqid('report-'),
            'name' => 'Reporte ' . ucfirst($validated['type']),
            'type' => $validated['type'],
            'period' => $validated['period'],
            'detail' => $validated['detail'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'generatedAt' => Carbon::now(),
        ];

        return response()->json([
            'success' => true,
            'data' => $reportData,
        ]);
    }

    /**
     * Exportar reporte
     */
    public function exportReport(Request $request)
    {
        $format = $request->query('format', 'pdf');
        $type = $request->query('type', 'ingresos');

        // La exportación real se implementaría con DomPDF o PhpSpreadsheet
        return response()->json([
            'success' => true,
            'message' => "Reporte de {$type} exportado en formato {$format}",
        ]);
    }

    /**
     * Obtener lista de reportes guardados
     */
    public function listReports(Request $request)
    {
        // Simulación - en implementación real, consultar tabla de reportes guardados
        $reports = [
            ['id' => 1, 'name' => 'Ingresos Abril 2026', 'type' => 'ingresos', 'date' => '2026-05-01', 'format' => 'pdf'],
            ['id' => 2, 'name' => 'Análisis de Citas Abril', 'type' => 'citas', 'date' => '2026-04-30', 'format' => 'pdf'],
            ['id' => 3, 'name' => 'Estado Inventario Marzo', 'type' => 'inventario', 'date' => '2026-03-31', 'format' => 'excel'],
        ];

        return response()->json([
            'success' => true,
            'data' => $reports,
        ]);
    }

    /**
     * Helper: Obtener fecha de inicio según período
     */
    private function getStartDate($period)
    {
        return match ($period) {
            'dia' => Carbon::now()->startOfDay(),
            'semana' => Carbon::now()->startOfWeek(),
            'mes' => Carbon::now()->startOfMonth(),
            'trimestre' => Carbon::now()->startOfQuarter(),
            'año' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };
    }

    /**
     * Helper: Obtener servicio más popular
     */
    private function getTopService($appointments)
    {
        // Implementar con tabla de servicios real
        return [
            'name' => 'Corte + Barba',
            'count' => $appointments->count(),
            'revenue' => $appointments->sum('price'),
        ];
    }
}
