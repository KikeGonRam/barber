<?php

namespace App\Http\Controllers\Api\Admin\Dashboard;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del dashboard principal del panel admin.
 * Expone estadísticas del día, próximas citas, ingresos por periodo,
 * alertas operativas y métricas generales del negocio.
 */
class DashboardAdminController
{
    // Defensa en profundidad: aunque la ruta ya exige role.custom:administrador,
    // este guard evita que un descuido en routes/api.php exponga métricas del negocio.
    private function authorizeAdmin(): void
    {
        abort_if(! request()->user()?->hasRole('administrador'), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    // Métricas rápidas del día: ingresos, citas completadas, ocupación y clientes nuevos
    public function getStats(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
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
                'revenueToday' => (float) $revenueToday,
                'appointmentsCompleted' => $appointmentsCompleted,
                'occupancyRate' => min($occupancyRate, 100),
                'newClients' => $newClients,
            ],
        ]);
    }

    // Próximas 10 citas pendientes a partir de ahora, ordenadas por fecha/hora
    public function getUpcomingAppointments(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $appointments = Appointment::where('fecha', '>=', now())
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

    // Ingresos agrupados por día dentro del periodo pedido (week/month/year)
    public function getRevenue(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $period = $request->get('period', 'week');

        $query = Appointment::where('estado', 'completada');

        if ($period === 'week') {
            $query->whereBetween('fecha', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]);
        } elseif ($period === 'month') {
            $query->whereBetween('fecha', [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]);
        } elseif ($period === 'year') {
            $query->whereBetween('fecha', [
                now()->startOfYear(),
                now()->endOfYear(),
            ]);
        }

        $appointments = $query->get(['fecha', 'precio_cobrado']);

        $revenue = [];
        foreach ($appointments as $appointment) {
            $date = $appointment->fecha instanceof Carbon
                ? $appointment->fecha->toDateString()
                : (string) $appointment->fecha;
            $revenue[$date] = ($revenue[$date] ?? 0) + (float) ($appointment->precio_cobrado ?? 0);
        }

        return response()->json([
            'revenue' => $revenue,
            'total' => array_sum($revenue),
        ]);
    }

    // Genera alertas operativas: citas próximas, stock bajo y baja ocupación de barberos
    public function getAlerts(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $alerts = [];

        $upcomingAppointments = Appointment::whereDate('fecha', now()->toDateString())
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->where('hora_inicio', '<=', now()->addHours(2)->format('H:i:s'))
            ->where('estado', 'pendiente')
            ->count();

        if ($upcomingAppointments > 0) {
            $alerts[] = [
                'id' => 1,
                'type' => 'warning',
                'title' => 'Citas Próximas',
                'message' => "$upcomingAppointments citas en las próximas 2 horas",
            ];
        }

        try {
            // Comparación entre dos campos del mismo documento: requiere $expr, no un where() normal
            $lowStock = Product::whereRaw(['$expr' => ['$lte' => ['$stock_actual', '$stock_minimo']]])->count();

            if ($lowStock > 0) {
                $alerts[] = [
                    'id' => 2,
                    'type' => 'error',
                    'title' => 'Inventario Bajo',
                    'message' => "$lowStock productos con stock bajo",
                ];
            }
        } catch (\Exception $e) {
            // Ignorar si la colección no tiene los campos esperados
        }

        // Batch: 1 query for all barbers' today counts instead of N+1
        $activeBarbers = Barber::where('activo', true)->get(['_id']);
        $activeBarberIds = $activeBarbers->pluck('id')->map(fn ($id) => (string) $id)->all();
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
                'id' => 3,
                'type' => 'info',
                'title' => 'Ocupación Baja',
                'message' => "{$lowOccupancyCount} barbero(s) con baja ocupación hoy",
            ];
        }

        return response()->json(['alerts' => $alerts]);
    }

    // Métricas globales del negocio: clientes, barberos activos, tasa de cancelación e ingresos
    public function getMetrics(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $totalClients = Client::count();
        $activeBarbers = Barber::where('activo', true)->count();

        $totalAppointments = Appointment::count();
        $cancelledAppointments = Appointment::where('estado', 'cancelada')->count();
        $cancellationRate = $totalAppointments > 0
            ? round(($cancelledAppointments / $totalAppointments) * 100, 2)
            : 0;

        $completedAppointments = Appointment::where('estado', 'completada')->count();
        $totalRevenue = (float) Appointment::where('estado', 'completada')->sum('precio_cobrado');
        $averageRevenue = $completedAppointments > 0
            ? round($totalRevenue / $completedAppointments, 2)
            : 0;

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
