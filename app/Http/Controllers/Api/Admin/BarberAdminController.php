<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarberAdminController
{
    public function getBarbers(Request $request): JsonResponse
    {
        $barbers   = Barber::with('user')->where('activo', true)->get();
        $barberIds = $barbers->pluck('id')->map(fn ($id) => (string) $id)->all();
        $today     = Carbon::today()->toDateString();

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
            $bId            = (string) $barber->id;
            $dayAppts       = $todayAppts->get($bId, collect());
            $apptCount      = $dayAppts->count();
            $revenueToday   = (float) $dayAppts->where('estado', 'completada')->sum('precio_cobrado');
            $totalClients   = $allBarberAppts->get($bId, collect())->pluck('client_id')->unique()->count();

            return [
                'id'              => $barber->id,
                'name'            => $barber->user?->name,
                'email'           => $barber->user?->email,
                'especialidades'  => $barber->especialidades,
                'foto'            => $barber->foto,
                'rating'          => $this->calculateRating((string) $barber->user_id),
                'appointmentsToday' => $apptCount,
                'maxAppointments' => 8,
                'occupancyRate'   => $apptCount > 0 ? (int) (($apptCount / 8) * 100) : 0,
                'revenueToday'    => $revenueToday,
                'totalClients'    => $totalClients,
                'activo'          => $barber->activo,
            ];
        });

        return response()->json(['success' => true, 'data' => $barbers]);
    }

    public function show(Barber $barber): JsonResponse
    {
        $barber->load('user');
        $barberId   = (string) $barber->id;
        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['estado', 'precio_cobrado']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                    => $barber->id,
                'slug'                  => $barber->slug,
                'name'                  => $barber->user?->name,
                'email'                 => $barber->user?->email,
                'especialidades'        => $barber->especialidades,
                'descripcion'           => $barber->descripcion,
                'foto'                  => $barber->foto,
                'rating'                => $this->calculateRating((string) $barber->user_id),
                'totalAppointments'     => Appointment::where('barber_id', $barberId)->count(),
                'appointmentsThisMonth' => $appointmentsMonth->count(),
                'revenueThisMonth'      => (float) $appointmentsMonth->where('estado', 'completada')->sum('precio_cobrado'),
                'totalClients'          => $this->getTotalClients($barberId),
                'createdAt'             => optional($barber->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function getSchedule(Barber $barber, Request $request): JsonResponse
    {
        $barberId = (string) $barber->id;
        $date     = $request->query('date', Carbon::today()->toDateString());

        $appointments = Appointment::where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->with(['client.user', 'service'])
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn ($a) => [
                'id'          => $a->id,
                'code'        => $a->code,
                'clientName'  => $a->client?->user?->name,
                'hora_inicio' => $a->hora_inicio,
                'hora_fin'    => $a->hora_fin,
                'service'     => $a->service?->nombre,
                'estado'      => $a->estado,
                'precio'      => $a->precio_cobrado,
            ]);

        return response()->json([
            'success'      => true,
            'date'         => $date,
            'appointments' => $appointments,
        ]);
    }

    public function getRegularClients(Barber $barber): JsonResponse
    {
        $barberId  = (string) $barber->id;
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
            $appts  = $apptsByClient->get((string) $clientId, collect());
            $client = $clientsMap->get((string) $clientId);
            return [
                'id'               => $clientId,
                'slug'             => $client?->slug,
                'name'             => $client?->user?->name ?? 'Desconocido',
                'appointmentCount' => $appts->count(),
                'totalSpent'       => (float) $appts->where('estado', 'completada')->sum('precio_cobrado'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function update(Barber $barber, Request $request): JsonResponse
    {
        $barber->load('user');
        $validated = $request->validate([
            'especialidades' => 'nullable|string|max:255',
            'descripcion'    => 'nullable|string',
            'activo'         => 'boolean',
        ]);

        $barber->update($validated);

        if ($request->has('name') || $request->has('email')) {
            $barber->user?->update($request->only(['name', 'email']));
        }

        return response()->json([
            'success' => true,
            'message' => 'Barbero actualizado correctamente',
            'data'    => $barber,
        ]);
    }

    public function getPerformanceStats(Barber $barber): JsonResponse
    {
        $barberId       = (string) $barber->id;
        $thisMonthStart = Carbon::now()->startOfMonth()->toDateString();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd   = Carbon::now()->subMonth()->endOfMonth()->toDateString();

        $thisMonth = Appointment::where('barber_id', $barberId)
            ->where('fecha', '>=', $thisMonthStart)
            ->count();

        $lastMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$lastMonthStart, $lastMonthEnd])
            ->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'appointmentsThisMonth' => $thisMonth,
                'appointmentsLastMonth' => $lastMonth,
                'growth'                => $lastMonth > 0
                    ? (int) ((($thisMonth - $lastMonth) / $lastMonth) * 100)
                    : 0,
                'averageRating' => $this->calculateRating((string) $barber->user_id),
                'totalClients'  => $this->getTotalClients($barberId),
            ],
        ]);
    }

    // Accept user_id (barbero_id on works) to avoid redundant Barber::find() calls
    private function calculateRating(string $barberUserId): float
    {
        if (! $barberUserId) {
            return 0.0;
        }

        $avg = Comment::whereHas('work', fn ($q) => $q->where('barbero_id', $barberUserId))
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : 0.0;
    }

    private function getTotalClients(string $barberId): int
    {
        return Appointment::where('barber_id', $barberId)
            ->get(['client_id'])
            ->pluck('client_id')
            ->unique()
            ->count();
    }
}
