<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportAdminController
{
    public function generateRevenueReport(Request $request): JsonResponse
    {
        $period    = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);
        $endDate   = Carbon::now();

        $appointments = Appointment::whereBetween('fecha', [$startDate->toDateString(), $endDate->toDateString()])
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
        $barberIds  = $appointments->pluck('barber_id')->filter()->unique()->values()->all();
        $barbersMap = \App\Models\Barber::with('user:id,name')
            ->find($barberIds)
            ->keyBy(fn ($b) => (string) $b->id);

        $revenueByBarber = $appointments->groupBy('barber_id')->map(function ($g, $barberId) use ($barbersMap) {
            $total = (float) $g->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));
            return [
                'barber'  => $barbersMap->get((string) $barberId)?->user?->name ?? 'Sin barbero',
                'total'   => $total,
                'count'   => $g->count(),
                'average' => $g->count() > 0 ? round($total / $g->count(), 2) : 0,
            ];
        });

        $totalRevenue = (float) $appointments->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

        return response()->json([
            'success' => true,
            'data'    => [
                'period'            => $period,
                'startDate'         => $startDate->toDateString(),
                'endDate'           => $endDate->toDateString(),
                'totalRevenue'      => $totalRevenue,
                'totalAppointments' => $appointments->count(),
                'averageRevenue'    => $appointments->count() > 0
                    ? round($totalRevenue / $appointments->count(), 2)
                    : 0,
                'dailyRevenue'      => $dailyRevenue,
                'revenueByBarber'   => $revenueByBarber,
            ],
        ]);
    }

    public function generateAppointmentsReport(Request $request): JsonResponse
    {
        $period    = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);
        $endDate   = Carbon::now();

        $appointments = Appointment::whereBetween('fecha', [$startDate->toDateString(), $endDate->toDateString()])
            ->get(['fecha', 'barber_id', 'estado']);

        $byStatus = [
            'completada' => $appointments->where('estado', 'completada')->count(),
            'pendiente'  => $appointments->where('estado', 'pendiente')->count(),
            'cancelada'  => $appointments->where('estado', 'cancelada')->count(),
            'no_asistio' => $appointments->where('estado', 'no_asistio')->count(),
        ];

        // Batch-load barber names (1 query instead of eager loading N relationships)
        $barberIds  = $appointments->pluck('barber_id')->filter()->unique()->values()->all();
        $barbersMap = \App\Models\Barber::with('user:id,name')
            ->find($barberIds)
            ->keyBy(fn ($b) => (string) $b->id);

        $byBarber = $appointments->groupBy('barber_id')->map(function ($g, $barberId) use ($barbersMap) {
            $completadas = $g->where('estado', 'completada')->count();
            return [
                'barber'        => $barbersMap->get((string) $barberId)?->user?->name ?? 'Sin barbero',
                'total'         => $g->count(),
                'completadas'   => $completadas,
                'canceladas'    => $g->where('estado', 'cancelada')->count(),
                'occupancyRate' => round(($completadas / max($g->count(), 1)) * 100),
            ];
        });

        $diffDays = max($startDate->diffInDays($endDate), 1);

        return response()->json([
            'success' => true,
            'data'    => [
                'period'                       => $period,
                'startDate'                    => $startDate->toDateString(),
                'endDate'                      => $endDate->toDateString(),
                'totalAppointments'            => $appointments->count(),
                'appointmentsByStatus'         => $byStatus,
                'completionRate'               => round(($byStatus['completada'] / max($appointments->count(), 1)) * 100),
                'appointmentsByBarber'         => $byBarber,
                'averageAppointmentsPerDay'    => round($appointments->count() / $diffDays, 1),
            ],
        ]);
    }

    public function generateInventoryReport(Request $request): JsonResponse
    {
        $products  = Product::all(['nombre', 'categoria', 'stock_actual', 'stock_minimo', 'precio_venta', 'precio_compra']);
        $movements = InventoryMovement::where('created_at', '>=', Carbon::now()->subMonth())
            ->with('product:id,nombre')
            ->get(['tipo', 'cantidad', 'product_id', 'created_at']);

        $byType = [
            'entrada' => $movements->where('tipo', 'entrada')->count(),
            'salida'  => $movements->where('tipo', 'salida')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => [
                'totalProducts'    => $products->count(),
                'totalValue'       => (float) $products->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                'lowStockCount'    => $products->filter(fn ($p) => ($p->stock_actual ?? 0) <= ($p->stock_minimo ?? 0))->count(),
                'criticalCount'    => $products->filter(fn ($p) => ($p->stock_actual ?? 0) < (($p->stock_minimo ?? 0) / 2))->count(),
                'productsByCategory' => $products->groupBy('categoria')->map(fn ($g) => [
                    'count'      => $g->count(),
                    'totalValue' => (float) $g->sum(fn ($p) => (float) ($p->stock_actual ?? 0) * (float) ($p->precio_venta ?? 0)),
                ]),
                'recentMovements'  => $movements->take(10)->map(fn ($m) => [
                    'product'  => $m->product?->nombre,
                    'tipo'     => $m->tipo,
                    'cantidad' => $m->cantidad,
                    'fecha'    => optional($m->created_at)->toIso8601String(),
                ]),
                'movementsByType'  => $byType,
            ],
        ]);
    }

    public function generateClientsReport(Request $request): JsonResponse
    {
        $period    = $request->query('period', 'mes');
        $startDate = $this->getStartDate($period);

        $totalClients = Client::count();
        $newClients   = Client::where('created_at', '>=', $startDate)->count();

        $activeClients = Client::whereHas('appointments', function ($q) use ($startDate) {
            $q->where('fecha', '>=', $startDate->toDateString());
        })->count();

        $inactiveClients = Client::whereDoesntHave('appointments', function ($q) {
            $q->where('fecha', '>=', Carbon::now()->subMonths(3)->toDateString());
        })->count();

        $loyalClients = Client::whereHas('appointments', function ($q) {
            $q->where('estado', 'completada');
        }, '>', 10)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'period'          => $period,
                'totalClients'    => $totalClients,
                'newClients'      => $newClients,
                'activeClients'   => $activeClients,
                'inactiveClients' => $inactiveClients,
                'loyaltyClients'  => $loyalClients,
                'clientRetention' => round(($activeClients / max($totalClients, 1)) * 100),
            ],
        ]);
    }

    public function generateCustomReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'     => 'required|in:ingresos,citas,inventario,clientes',
            'period'   => 'required|in:dia,semana,mes,trimestre,año,personalizado',
            'dateFrom' => 'nullable|date',
            'dateTo'   => 'nullable|date',
            'detail'   => 'required|in:resumen,detallado,muy-detallado',
        ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => uniqid('report-'),
                'name'        => 'Reporte ' . ucfirst($validated['type']),
                'type'        => $validated['type'],
                'period'      => $validated['period'],
                'detail'      => $validated['detail'],
                'startDate'   => $validated['dateFrom'] ?? $this->getStartDate($validated['period'])->toDateString(),
                'endDate'     => $validated['dateTo'] ?? Carbon::now()->toDateString(),
                'generatedAt' => Carbon::now()->toIso8601String(),
            ],
        ]);
    }

    public function exportReport(Request $request): JsonResponse
    {
        $format = $request->query('format', 'pdf');
        $type   = $request->query('type', 'ingresos');

        return response()->json([
            'success' => true,
            'message' => "Reporte de {$type} exportado en formato {$format}",
        ]);
    }

    public function listReports(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            'dia'        => Carbon::now()->startOfDay(),
            'semana'     => Carbon::now()->startOfWeek(),
            'trimestre'  => Carbon::now()->startOfQuarter(),
            'año'        => Carbon::now()->startOfYear(),
            default      => Carbon::now()->startOfMonth(),
        };
    }
}
