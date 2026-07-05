<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardAdminController
{
    public function getStats(Request $request): JsonResponse
    {
        $today = now()->toDateString();

        $revenueToday = Appointment::whereDate('fecha', $today)
            ->where('estado', 'completada')
            ->sum('precio_cobrado');

        $appointmentsCompleted = Appointment::whereDate('fecha', $today)
            ->where('estado', 'completada')
            ->count();

        $totalBarbers = Barber::where('activo', true)->count();
        $appointmentsTotal = Appointment::whereDate('fecha', $today)->count();
        $occupancyRate = $totalBarbers > 0 ? round(($appointmentsTotal / ($totalBarbers * 8)) * 100) : 0;

        $newClients = Client::whereDate('created_at', $today)->count();

        return response()->json([
            'stats' => [
                'revenueToday'          => (float) $revenueToday,
                'appointmentsCompleted' => $appointmentsCompleted,
                'occupancyRate'         => min($occupancyRate, 100),
                'newClients'            => $newClients,
            ],
        ]);
    }

    public function getUpcomingAppointments(Request $request): JsonResponse
    {
        $appointments = Appointment::where('fecha', '>=', now()->toDateString())
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->where('estado', 'pendiente')
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->with(['client.user', 'barber.user', 'service'])
            ->limit(10)
            ->get();

        return response()->json([
            'appointments' => $appointments,
        ]);
    }

    public function getRevenue(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');

        $query = Appointment::where('estado', 'completada');

        if ($period === 'week') {
            $query->whereBetween('fecha', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString(),
            ]);
        } elseif ($period === 'month') {
            $query->whereBetween('fecha', [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ]);
        } elseif ($period === 'year') {
            $query->whereBetween('fecha', [
                now()->startOfYear()->toDateString(),
                now()->endOfYear()->toDateString(),
            ]);
        }

        $appointments = $query->get(['fecha', 'precio_cobrado']);

        $revenue = [];
        foreach ($appointments as $appointment) {
            $date = $appointment->fecha instanceof \Carbon\Carbon
                ? $appointment->fecha->toDateString()
                : (string) $appointment->fecha;
            $revenue[$date] = ($revenue[$date] ?? 0) + (float) ($appointment->precio_cobrado ?? 0);
        }

        return response()->json([
            'revenue' => $revenue,
            'total'   => array_sum($revenue),
        ]);
    }

    public function getAlerts(Request $request): JsonResponse
    {
        $alerts = [];

        $upcomingAppointments = Appointment::whereDate('fecha', now()->toDateString())
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->where('hora_inicio', '<=', now()->addHours(2)->format('H:i:s'))
            ->where('estado', 'pendiente')
            ->count();

        if ($upcomingAppointments > 0) {
            $alerts[] = [
                'id'      => 1,
                'type'    => 'warning',
                'title'   => '⏰ Citas Próximas',
                'message' => "$upcomingAppointments citas en las próximas 2 horas",
            ];
        }

        try {
            $lowStock = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

            if ($lowStock > 0) {
                $alerts[] = [
                    'id'      => 2,
                    'type'    => 'error',
                    'title'   => '📦 Inventario Bajo',
                    'message' => "$lowStock productos con stock bajo",
                ];
            }
        } catch (\Exception $e) {
            // Ignorar si la colección no tiene los campos esperados
        }

        // Batch: 1 query for all barbers' today counts instead of N+1
        $activeBarbers     = Barber::where('activo', true)->get(['_id']);
        $activeBarberIds   = $activeBarbers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $todayCountsByBarber = Appointment::whereDate('fecha', now()->toDateString())
            ->whereIn('barber_id', $activeBarberIds)
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map(fn ($g) => $g->count());
        $lowOccupancyCount = $activeBarbers->filter(
            fn ($b) => ($todayCountsByBarber->get((string) $b->id) ?? 0) < 2
        )->count();

        if ($lowOccupancyCount > 0) {
            $alerts[] = [
                'id'      => 3,
                'type'    => 'info',
                'title'   => 'Ocupación Baja',
                'message' => "{$lowOccupancyCount} barbero(s) con baja ocupación hoy",
            ];
        }

        return response()->json(['alerts' => $alerts]);
    }

    public function getMetrics(Request $request): JsonResponse
    {
        $totalClients   = Client::count();
        $activeBarbers  = Barber::where('activo', true)->count();

        $totalAppointments     = Appointment::count();
        $cancelledAppointments = Appointment::where('estado', 'cancelada')->count();
        $cancellationRate      = $totalAppointments > 0
            ? round(($cancelledAppointments / $totalAppointments) * 100, 2)
            : 0;

        $completedAppointments = Appointment::where('estado', 'completada')->count();
        $totalRevenue          = (float) Appointment::where('estado', 'completada')->sum('precio_cobrado');
        $averageRevenue        = $completedAppointments > 0
            ? round($totalRevenue / $completedAppointments, 2)
            : 0;

        return response()->json([
            'metrics' => [
                'totalClients'                   => $totalClients,
                'activeBarbers'                  => $activeBarbers,
                'cancellationRate'               => $cancellationRate,
                'averageRevenuePerAppointment'   => $averageRevenue,
                'totalRevenue'                   => $totalRevenue,
            ],
        ]);
    }
}
