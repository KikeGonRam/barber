<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class BarberAdminController extends Controller
{
    /**
     * Obtener lista de barberos con métricas de performance
     */
    public function getBarbers(Request $request)
    {
        $barbers = User::whereHas('roles', function ($query) {
            $query->where('name', 'barbero');
        })
            ->with(['appointments' => function ($query) {
                $query->where('date', Carbon::today());
            }])
            ->get()
            ->map(function ($barber) {
                $appointmentsToday = $barber->appointments->count();
                $revenueToday = $barber->appointments->sum('price');

                return [
                    'id' => $barber->id,
                    'name' => $barber->name,
                    'email' => $barber->email,
                    'phone' => $barber->phone,
                    'specialties' => $barber->specialties ?? 'General',
                    'rating' => $this->calculateRating($barber->id),
                    'ratingCount' => $this->getRatingCount($barber->id),
                    'appointmentsToday' => $appointmentsToday,
                    'maxAppointments' => 8,
                    'occupancyRate' => $appointmentsToday > 0 ? intval(($appointmentsToday / 8) * 100) : 0,
                    'revenueToday' => $revenueToday,
                    'totalClients' => $this->getTotalClients($barber->id),
                    'avgServiceTime' => 30,
                    'active' => $barber->active ?? true,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $barbers,
        ]);
    }

    /**
     * Obtener detalles de un barbero específico
     */
    public function show($barberId)
    {
        $barber = User::findOrFail($barberId);

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('date', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $barber->id,
                'name' => $barber->name,
                'email' => $barber->email,
                'phone' => $barber->phone,
                'specialties' => $barber->specialties,
                'bio' => $barber->bio,
                'photo' => $barber->photo_url,
                'rating' => $this->calculateRating($barber->id),
                'totalAppointments' => Appointment::where('barber_id', $barberId)->count(),
                'appointmentsThisMonth' => $appointmentsMonth->count(),
                'revenueThisMonth' => $appointmentsMonth->sum('price'),
                'totalClients' => $this->getTotalClients($barber->id),
                'averageRating' => $this->calculateRating($barber->id),
                'createdAt' => $barber->created_at,
            ],
        ]);
    }

    /**
     * Obtener agenda/horario de un barbero
     */
    public function getSchedule($barberId, Request $request)
    {
        $date = $request->query('date', Carbon::today());

        $appointments = Appointment::where('barber_id', $barberId)
            ->where('date', $date)
            ->with('client')
            ->get()
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'clientName' => $appointment->client?->name,
                    'time' => $appointment->time,
                    'duration' => $appointment->duration ?? 30,
                    'service' => $appointment->service_id,
                    'status' => $appointment->status,
                    'price' => $appointment->price,
                ];
            });

        return response()->json([
            'success' => true,
            'date' => $date,
            'appointments' => $appointments,
        ]);
    }

    /**
     * Obtener clientes regulares de un barbero
     */
    public function getRegularClients($barberId)
    {
        $clients = Appointment::where('barber_id', $barberId)
            ->select('client_id')
            ->distinct()
            ->with('client')
            ->limit(20)
            ->get()
            ->pluck('client')
            ->map(function ($client) use ($barberId) {
                $clientAppointments = Appointment::where('client_id', $client->id)
                    ->where('barber_id', $barberId)
                    ->count();

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'appointmentCount' => $clientAppointments,
                    'totalSpent' => Appointment::where('client_id', $client->id)
                        ->where('barber_id', $barberId)
                        ->sum('price'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Actualizar información del barbero
     */
    public function update($barberId, Request $request)
    {
        $barber = User::findOrFail($barberId);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $barberId,
            'phone' => 'string|max:20',
            'specialties' => 'string|max:255',
            'bio' => 'string|nullable',
            'active' => 'boolean',
        ]);

        $barber->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Barbero actualizado correctamente',
            'data' => $barber,
        ]);
    }

    /**
     * Obtener estadísticas de desempeño
     */
    public function getPerformanceStats($barberId)
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        $thisMonthAppointments = Appointment::where('barber_id', $barberId)
            ->whereBetween('date', [$thisMonth, Carbon::now()])
            ->count();

        $lastMonthAppointments = Appointment::where('barber_id', $barberId)
            ->whereBetween('date', [$lastMonth, $lastMonth->copy()->endOfMonth()])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'appointmentsThisMonth' => $thisMonthAppointments,
                'appointmentsLastMonth' => $lastMonthAppointments,
                'growth' => $lastMonthAppointments > 0
                    ? intval((($thisMonthAppointments - $lastMonthAppointments) / $lastMonthAppointments) * 100)
                    : 0,
                'averageRating' => $this->calculateRating($barberId),
                'totalClients' => $this->getTotalClients($barberId),
            ],
        ]);
    }

    /**
     * Calcular calificación promedio
     */
    private function calculateRating($barberId)
    {
        // Implementar lógica real con tabla de ratings
        return 4.7; // Demo
    }

    /**
     * Obtener cantidad de ratings
     */
    private function getRatingCount($barberId)
    {
        // Implementar lógica real con tabla de ratings
        return 38; // Demo
    }

    /**
     * Obtener total de clientes únicos
     */
    private function getTotalClients($barberId)
    {
        return Appointment::where('barber_id', $barberId)
            ->select('client_id')
            ->distinct()
            ->count();
    }
}
