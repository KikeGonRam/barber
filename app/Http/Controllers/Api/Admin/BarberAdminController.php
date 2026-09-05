<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberReview;
use App\Models\Client;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de administración de barberos (panel admin).
 * Expone listado, detalle, agenda, clientes recurrentes, edición y estadísticas
 * de desempeño de cada barbero, con métricas agregadas (ingresos, ocupación, rating).
 */
class BarberAdminController
{
    // Defensa en profundidad: aunque la ruta ya exige role.custom:administrador,
    // este guard evita que un descuido en routes/api.php exponga datos de barberos.
    private function authorizeAdmin(): void
    {
        abort_if(! request()->user()?->hasRole('administrador'), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    // Devuelve el listado de barberos activos con métricas del día (citas, ingresos, clientes totales)
    public function getBarbers(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $barbers = Barber::with('user')->where('activo', true)->get();
        $barberIds = $barbers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $today = Carbon::today()->toDateString();

        // Batch today's appointments for all barbers (2 queries instead of 2N)
        $todayAppts = Appointment::whereIn('barber_id', $barberIds)
            ->whereDate('fecha', $today)
            ->get(['barber_id', 'estado', 'precio_cobrado'])
            ->groupBy('barber_id');

        // Batch all client IDs for unique client count per barber (1 query instead of N)
        $allBarberAppts = Appointment::whereIn('barber_id', $barberIds)
            ->get(['barber_id', 'client_id'])
            ->groupBy('barber_id');

        $barbers = $barbers->map(function (Barber $barber) use ($todayAppts, $allBarberAppts) {
            $bId = (string) $barber->id;
            $dayAppts = $todayAppts->get($bId, collect());
            $apptCount = $dayAppts->count();
            $revenueToday = (float) $dayAppts->where('estado', 'completada')->sum('precio_cobrado');
            $totalClients = $allBarberAppts->get($bId, collect())->pluck('client_id')->unique()->count();

            return [
                'id' => $barber->id,
                'name' => $barber->user?->name,
                'email' => $barber->user?->email,
                'especialidades' => $barber->especialidades,
                'foto' => $barber->foto,
                'rating' => $this->calculateRating((string) $barber->id),
                'appointmentsToday' => $apptCount,
                'maxAppointments' => 8,
                'occupancyRate' => $apptCount > 0 ? (int) (($apptCount / 8) * 100) : 0,
                'revenueToday' => $revenueToday,
                'totalClients' => $totalClients,
                'activo' => $barber->activo,
            ];
        });

        return response()->json(['success' => true, 'data' => $barbers]);
    }

    // Devuelve el perfil completo de un barbero con estadísticas del mes actual
    public function show(Barber $barber): JsonResponse
    {
        $this->authorizeAdmin();
        $barber->load('user');
        $barberId = (string) $barber->id;
        // Carbon objects, no strings: whereBetween contra 'fecha' (cast 'date',
        // guardado como BSON UTCDateTime) no hace match si se le pasa un
        // string — ver el mismo comentario en BarberDashboardController.
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['estado', 'precio_cobrado']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $barber->id,
                'slug' => $barber->slug,
                'name' => $barber->user?->name,
                'email' => $barber->user?->email,
                'especialidades' => $barber->especialidades,
                'descripcion' => $barber->descripcion,
                'foto' => $barber->foto,
                'rating' => $this->calculateRating((string) $barber->id),
                'totalAppointments' => Appointment::where('barber_id', $barberId)->count(),
                'appointmentsThisMonth' => $appointmentsMonth->count(),
                'revenueThisMonth' => (float) $appointmentsMonth->where('estado', 'completada')->sum('precio_cobrado'),
                'totalClients' => $this->getTotalClients($barberId),
                'createdAt' => optional($barber->created_at)->toIso8601String(),
            ],
        ]);
    }

    // Devuelve la agenda de citas de un barbero para una fecha específica (por defecto hoy)
    public function getSchedule(Barber $barber, Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $barberId = (string) $barber->id;
        $date = $request->query('date', Carbon::today()->toDateString());

        $appointments = Appointment::where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->with(['client.user', 'service'])
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'clientName' => $a->client?->user?->name,
                'hora_inicio' => $a->hora_inicio,
                'hora_fin' => $a->hora_fin,
                'service' => $a->service?->nombre,
                'estado' => $a->estado,
                'precio' => $a->precio_cobrado,
            ]);

        return response()->json([
            'success' => true,
            'date' => $date,
            'appointments' => $appointments,
        ]);
    }

    // Devuelve hasta 20 clientes recurrentes del barbero, con total de citas y gasto acumulado
    public function getRegularClients(Barber $barber): JsonResponse
    {
        $this->authorizeAdmin();
        $barberId = (string) $barber->id;
        // Se limita a 20 clientes para evitar cargar el historial completo cuando hay muchos
        $clientIds = Appointment::where('barber_id', $barberId)
            ->get(['client_id'])
            ->pluck('client_id')
            ->unique()
            ->take(20)
            ->values();

        // Batch: load all appointments for these clients with this barber (1 query instead of 2N)
        $apptsByClient = Appointment::where('barber_id', $barberId)
            ->whereIn('client_id', $clientIds->all())
            ->get(['client_id', 'estado', 'precio_cobrado'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        // Batch: load all client profiles (1 query instead of N)
        $clientsMap = Client::with('user')
            ->whereIn('_id', $clientIds->all())
            ->get()
            ->keyBy(fn ($c) => (string) $c->id);

        $clients = $clientIds->map(function ($clientId) use ($apptsByClient, $clientsMap) {
            $appts = $apptsByClient->get((string) $clientId, collect());
            $client = $clientsMap->get((string) $clientId);

            return [
                'id' => $clientId,
                'slug' => $client?->slug,
                'name' => $client?->user?->name ?? 'Desconocido',
                'appointmentCount' => $appts->count(),
                'totalSpent' => (float) $appts->where('estado', 'completada')->sum('precio_cobrado'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    // Actualiza datos del perfil de barbero y, opcionalmente, datos del usuario asociado (nombre/email)
    public function update(Barber $barber, Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $barber->load('user');
        $validated = $request->validate([
            'especialidades' => 'nullable|string|max:255',
            'descripcion' => 'nullable|string',
            'activo' => 'boolean',
        ]);

        $barber->update($validated);

        // name/email pertenecen al modelo User, no a Barber, por eso se actualizan aparte
        if ($request->has('name') || $request->has('email')) {
            $barber->user?->update($request->only(['name', 'email']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Barbero actualizado correctamente',
            'data' => $barber,
        ]);
    }

    // Compara el número de citas del mes actual contra el mes anterior y calcula el % de crecimiento
    public function getPerformanceStats(Barber $barber): JsonResponse
    {
        $this->authorizeAdmin();
        $barberId = (string) $barber->id;
        // Carbon objects, no strings: ver el comentario equivalente en show().
        $thisMonthStart = Carbon::now()->startOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        $thisMonth = Appointment::where('barber_id', $barberId)
            ->where('fecha', '>=', $thisMonthStart)
            ->count();

        $lastMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$lastMonthStart, $lastMonthEnd])
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'appointmentsThisMonth' => $thisMonth,
                'appointmentsLastMonth' => $lastMonth,
                'growth' => $lastMonth > 0
                    ? (int) ((($thisMonth - $lastMonth) / $lastMonth) * 100)
                    : 0,
                'averageRating' => $this->calculateRating((string) $barber->id),
                'totalClients' => $this->getTotalClients($barberId),
            ],
        ]);
    }

    // Promedio de las reseñas reales del barbero (BarberReview), no de los
    // comentarios del muro social (Comment): eran dos cosas distintas y esta
    // función usaba la equivocada (ver App\Models\BarberReview / Comment).
    private function calculateRating(string $barberId): float
    {
        if (! $barberId) {
            return 0.0;
        }

        $avg = BarberReview::where('barber_id', $barberId)->avg('rating');

        return $avg ? round((float) $avg, 1) : 0.0;
    }

    // Cuenta clientes únicos atendidos por el barbero a partir del historial de citas
    private function getTotalClients(string $barberId): int
    {
        return Appointment::where('barber_id', $barberId)
            ->get(['client_id'])
            ->pluck('client_id')
            ->unique()
            ->count();
    }
}
