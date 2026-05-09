<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Barber;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardAdminController
{
    /**
     * Obtener estadísticas del dashboard
     */
    public function getStats(Request $request): JsonResponse
    {
        $today = now()->format('Y-m-d');

        // Ingresos de hoy
        $revenueToday = Appointment::whereDate('start_time', $today)
            ->where('status', 'completada')
            ->with('service')
            ->get()
            ->sum(function ($appointment) {
                return $appointment->service->precio ?? 0;
            });

        // Citas completadas hoy
        $appointmentsCompleted = Appointment::whereDate('start_time', $today)
            ->where('status', 'completada')
            ->count();

        // Ocupación promedio
        $totalBarbers = Barber::where('activo', true)->count();
        $appointmentsTotal = Appointment::whereDate('start_time', $today)->count();
        $occupancyRate = $totalBarbers > 0 ? round(($appointmentsTotal / ($totalBarbers * 8)) * 100) : 0;

        // Clientes nuevos hoy
        $newClients = User::whereDate('created_at', $today)
            ->where('role', 'cliente')
            ->count();

        return response()->json([
            'stats' => [
                'revenueToday' => $revenueToday,
                'appointmentsCompleted' => $appointmentsCompleted,
                'occupancyRate' => min($occupancyRate, 100),
                'newClients' => $newClients,
            ],
        ]);
    }

    /**
     * Obtener citas próximas
     */
    public function getUpcomingAppointments(Request $request): JsonResponse
    {
        $appointments = Appointment::where('start_time', '>=', now())
            ->orderBy('start_time', 'asc')
            ->with('client.user', 'barber.user', 'service')
            ->limit(10)
            ->get();

        return response()->json([
            'appointments' => $appointments,
        ]);
    }

    /**
     * Obtener ingresos por rango de fechas
     */
    public function getRevenue(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week'); // week, month, year

        $query = Appointment::where('status', 'completada')
            ->with('service');

        if ($period === 'week') {
            $query->whereBetween('start_time', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]);
        } elseif ($period === 'month') {
            $query->whereBetween('start_time', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        } elseif ($period === 'year') {
            $query->whereBetween('start_time', [
                now()->startOfYear(),
                now()->endOfYear(),
            ]);
        }

        $appointments = $query->get();

        // Agrupar por fecha
        $revenue = [];
        foreach ($appointments as $appointment) {
            $date = $appointment->start_time->format('Y-m-d');
            if (!isset($revenue[$date])) {
                $revenue[$date] = 0;
            }
            $revenue[$date] += $appointment->service->precio ?? 0;
        }

        return response()->json([
            'revenue' => $revenue,
            'total' => array_sum($revenue),
        ]);
    }

    /**
     * Obtener alertas del sistema
     */
    public function getAlerts(Request $request): JsonResponse
    {
        $alerts = [];

        // Citas próximas en 2 horas
        $upcomingAppointments = Appointment::whereBetween('start_time', [
            now(),
            now()->addHours(2),
        ])
            ->where('status', 'pendiente')
            ->count();

        if ($upcomingAppointments > 0) {
            $alerts[] = [
                'id' => 1,
                'type' => 'warning',
                'title' => '⏰ Citas Próximas',
                'message' => "$upcomingAppointments citas en las próximas 2 horas",
            ];
        }

        // Inventario bajo (si existe tabla inventory)
        try {
            $lowStock = \DB::table('inventory')
                ->whereRaw('quantity < minimum_quantity')
                ->count();

            if ($lowStock > 0) {
                $alerts[] = [
                    'id' => 2,
                    'type' => 'error',
                    'title' => '📦 Inventario Bajo',
                    'message' => "$lowStock productos con stock bajo",
                ];
            }
        } catch (\Exception $e) {
            // Tabla de inventory no existe, ignorar
        }

        // Barberos con baja ocupación
        $lowOccupancy = Barber::where('activo', true)
            ->get()
            ->filter(function ($barber) {
                $today = now()->format('Y-m-d');
                $appointments = Appointment::whereDate('start_time', $today)
                    ->where('barber_id', $barber->id)
                    ->count();

                // Si tiene menos de 2 citas, es baja ocupación
                return $appointments < 2;
            });

        if ($lowOccupancy->count() > 0) {
            $alerts[] = [
                'id' => 3,
                'type' => 'info',
                'title' => '📊 Ocupación Baja',
                'message' => "{$lowOccupancy->count()} barbero(s) con baja ocupación hoy",
            ];
        }

        return response()->json([
            'alerts' => $alerts,
        ]);
    }

    /**
     * Obtener métricas de performance
     */
    public function getMetrics(Request $request): JsonResponse
    {
        $today = now()->format('Y-m-d');

        // Total de clientes
        $totalClients = User::where('role', 'cliente')->count();

        // Total de barberos activos
        $activeBarbers = Barber::where('activo', true)->count();

        // Tasa de cancelación
        $totalAppointments = Appointment::count();
        $cancelledAppointments = Appointment::where('status', 'cancelada')->count();
        $cancellationRate = $totalAppointments > 0 ? round(($cancelledAppointments / $totalAppointments) * 100, 2) : 0;

        // Ingreso promedio por cita
        $totalRevenue = Appointment::where('status', 'completada')
            ->with('service')
            ->get()
            ->sum(function ($appointment) {
                return $appointment->service->precio ?? 0;
            });

        $completedAppointments = Appointment::where('status', 'completada')->count();
        $averageRevenue = $completedAppointments > 0 ? round($totalRevenue / $completedAppointments, 2) : 0;

        return response()->json([
            'metrics' => [
                'totalClients' => $totalClients,
                'activeBarbers' => $activeBarbers,
                'cancellationRate' => $cancellationRate,
                'averageRevenuePerAppointment' => $averageRevenue,
                'totalRevenue' => $totalRevenue,
            ],
        ]);
    }
}
