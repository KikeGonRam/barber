<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Comment;
use App\Models\Work;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarberAdminController
{
    public function getBarbers(Request $request): JsonResponse
    {
        $barbers = Barber::with('user')
            ->where('activo', true)
            ->get()
            ->map(function (Barber $barber) {
                $today = Carbon::today()->toDateString();
                $appointmentsToday = Appointment::where('barber_id', (string) $barber->id)
                    ->whereDate('fecha', $today)
                    ->count();
                $revenueToday = (float) Appointment::where('barber_id', (string) $barber->id)
                    ->whereDate('fecha', $today)
                    ->where('estado', 'completada')
                    ->sum('precio_cobrado');

                return [
                    'id'              => $barber->id,
                    'name'            => $barber->user?->name,
                    'email'           => $barber->user?->email,
                    'especialidades'  => $barber->especialidades,
                    'foto'            => $barber->foto,
                    'rating'          => $this->calculateRating((string) $barber->id),
                    'appointmentsToday' => $appointmentsToday,
                    'maxAppointments' => 8,
                    'occupancyRate'   => $appointmentsToday > 0 ? (int) (($appointmentsToday / 8) * 100) : 0,
                    'revenueToday'    => $revenueToday,
                    'totalClients'    => $this->getTotalClients((string) $barber->id),
                    'activo'          => $barber->activo,
                ];
            });

        return response()->json(['success' => true, 'data' => $barbers]);
    }

    public function show($barberId): JsonResponse
    {
        $barber = Barber::with('user')->findOrFail($barberId);

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd   = Carbon::now()->endOfMonth()->toDateString();

        $appointmentsMonth = Appointment::where('barber_id', $barberId)
            ->whereBetween('fecha', [$monthStart, $monthEnd])
            ->get(['estado', 'precio_cobrado']);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                    => $barber->id,
                'name'                  => $barber->user?->name,
                'email'                 => $barber->user?->email,
                'especialidades'        => $barber->especialidades,
                'descripcion'           => $barber->descripcion,
                'foto'                  => $barber->foto,
                'rating'                => $this->calculateRating((string) $barber->id),
                'totalAppointments'     => Appointment::where('barber_id', $barberId)->count(),
                'appointmentsThisMonth' => $appointmentsMonth->count(),
                'revenueThisMonth'      => (float) $appointmentsMonth->where('estado', 'completada')->sum('precio_cobrado'),
                'totalClients'          => $this->getTotalClients((string) $barber->id),
                'createdAt'             => optional($barber->created_at)->toIso8601String(),
            ],
        ]);
    }

    public function getSchedule($barberId, Request $request): JsonResponse
    {
        $date = $request->query('date', Carbon::today()->toDateString());

        $appointments = Appointment::where('barber_id', $barberId)
            ->whereDate('fecha', $date)
            ->with(['client.user', 'service'])
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'clientName' => $a->client?->user?->name,
                'hora_inicio'=> $a->hora_inicio,
                'hora_fin'   => $a->hora_fin,
                'service'    => $a->service?->nombre,
                'estado'     => $a->estado,
                'precio'     => $a->precio_cobrado,
            ]);

        return response()->json([
            'success'      => true,
            'date'         => $date,
            'appointments' => $appointments,
        ]);
    }

    public function getRegularClients($barberId): JsonResponse
    {
        $clientIds = Appointment::where('barber_id', $barberId)
            ->get(['client_id'])
            ->pluck('client_id')
            ->unique()
            ->take(20);

        $clients = $clientIds->map(function ($clientId) use ($barberId) {
            $count = Appointment::where('client_id', $clientId)
                ->where('barber_id', $barberId)
                ->count();
            $spent = (float) Appointment::where('client_id', $clientId)
                ->where('barber_id', $barberId)
                ->where('estado', 'completada')
                ->sum('precio_cobrado');

            $client = \App\Models\Client::with('user')->find($clientId);

            return [
                'id'               => $clientId,
                'name'             => $client?->user?->name ?? 'Desconocido',
                'appointmentCount' => $count,
                'totalSpent'       => $spent,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $clients]);
    }

    public function update($barberId, Request $request): JsonResponse
    {
        $barber    = Barber::with('user')->findOrFail($barberId);
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

    public function getPerformanceStats($barberId): JsonResponse
    {
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
                'averageRating' => $this->calculateRating((string) $barberId),
                'totalClients'  => $this->getTotalClients((string) $barberId),
            ],
        ]);
    }

    private function calculateRating(string $barberId): float
    {
        $barber = Barber::find($barberId);
        if (! $barber) return 0.0;

        $avg = Comment::whereHas('work', fn ($q) => $q->where('barbero_id', $barber->user_id))
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
