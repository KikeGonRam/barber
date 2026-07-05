<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientAdminController
{
    public function getClients(Request $request): JsonResponse
    {
        $search  = $request->query('search', '');
        $segment = $request->query('segment');

        $query = Client::with('user');
        if ($search) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            );
        }
        $clients    = $query->get();
        $clientIds  = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query for all appointments of all clients (was N queries via enrichClientData)
        $allAppts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id', 'estado', 'precio_cobrado', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        $now     = Carbon::now();
        $enriched = $clients->map(fn ($c) => $this->enrichFromBatch($c, $allAppts, $now));

        if ($segment) {
            $enriched = $enriched->filter(fn ($c) => $c['segment'] === $segment)->values();
        }

        return response()->json([
            'success' => true,
            'data'    => $enriched,
            'total'   => $enriched->count(),
        ]);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load('user');
        $clientId = (string) $client->id;

        $appointments = Appointment::where('client_id', $clientId)
            ->with(['barber.user', 'service'])
            ->orderBy('fecha', 'desc')
            ->get();

        $totalSpent = (float) $appointments->where('estado', 'completada')
            ->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

        $segment = $this->computeSegment($client, $appointments->count(), $appointments->first()?->fecha);

        $preferredBarber = 'N/A';
        $grouped     = $appointments->groupBy(fn ($a) => (string) $a->barber_id)->map->count()->sortDesc();
        $topBarberId = $grouped->keys()->first();
        if ($topBarberId) {
            $topAppt         = $appointments->first(fn ($a) => (string) $a->barber_id === $topBarberId);
            $preferredBarber = $topAppt?->barber?->user?->name ?? 'N/A';
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                        => $client->id,
                'slug'                      => $client->slug,
                'name'                      => $client->user?->name,
                'email'                     => $client->user?->email,
                'telefono'                  => $client->telefono,
                'segment'                   => $segment,
                'joinedAt'                  => optional($client->created_at)->toIso8601String(),
                'totalAppointments'         => $appointments->count(),
                'totalSpent'                => $totalSpent,
                'averageSpent'              => $appointments->count() > 0 ? round($totalSpent / $appointments->count(), 2) : 0,
                'lastAppointment'           => optional($appointments->first()?->fecha)->toDateString(),
                'daysSinceLastAppointment'  => $appointments->isNotEmpty()
                    ? optional($appointments->first()->fecha)->diffInDays(Carbon::now())
                    : null,
                'preferredBarber'           => $preferredBarber,
                'appointments'              => $appointments->map(fn ($a) => [
                    'id'          => $a->id,
                    'code'        => $a->code,
                    'fecha'       => optional($a->fecha)->toDateString(),
                    'hora_inicio' => $a->hora_inicio,
                    'barber'      => $a->barber?->user?->name,
                    'service'     => $a->service?->nombre,
                    'precio'      => $a->precio_cobrado,
                    'estado'      => $a->estado,
                ]),
            ],
        ]);
    }

    public function getSegmentation(): JsonResponse
    {
        $clients   = Client::with('user')->get();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query instead of 2N queries
        $allAppts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        $now      = Carbon::now();
        $segments = ['vip' => 0, 'new' => 0, 'inactive' => 0, 'active' => 0];

        foreach ($clients as $client) {
            $appts     = $allAppts->get((string) $client->id, collect());
            $lastFecha = $appts->sortByDesc(fn ($a) => (string) $a->fecha)->first()?->fecha;
            $seg       = $this->computeSegment($client, $appts->count(), $lastFecha);
            $segments[$seg] = ($segments[$seg] ?? 0) + 1;
        }

        $total = $clients->count();

        return response()->json([
            'success' => true,
            'data'    => collect($segments)->map(fn ($count, $key) => [
                'count'      => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->assignRole('cliente');

        $client = $user->clientProfile()->create([
            'telefono' => $validated['telefono'] ?? null,
        ]);

        // No extra queries — compute from zero appointments
        $enriched = $this->enrichFromBatch($client->load('user'), collect(), Carbon::now());

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado correctamente',
            'data'    => $enriched,
        ], 201);
    }

    public function update(Client $client, Request $request): JsonResponse
    {
        $client->load('user');

        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'email'    => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
        ]);

        if (! empty($validated['name']) || ! empty($validated['email'])) {
            $client->user?->update(array_filter([
                'name'  => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ]));
        }

        if (isset($validated['telefono'])) {
            $client->update(['telefono' => $validated['telefono']]);
        }

        $client->refresh()->load('user');
        $appts = Appointment::where('client_id', (string) $client->id)
            ->get(['client_id', 'estado', 'precio_cobrado', 'fecha']);
        $grouped  = collect([(string) $client->id => $appts]);
        $enriched = $this->enrichFromBatch($client, $grouped, Carbon::now());

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente',
            'data'    => $enriched,
        ]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->load('user');
        $client->user?->delete();
        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    public function export(Request $request)
    {
        $clients   = Client::with('user')->get();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query for all appointment counts (was N queries)
        $apptCounts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id'])
            ->groupBy(fn ($a) => (string) $a->client_id)
            ->map(fn ($g) => $g->count());

        $filename = 'clients_' . date('Y-m-d') . '.csv';

        return response()->stream(function () use ($clients, $apptCounts) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nombre', 'Email', 'Teléfono', 'Total Citas']);

            foreach ($clients as $client) {
                fputcsv($handle, [
                    $client->id,
                    $client->user?->name,
                    $client->user?->email,
                    $client->telefono,
                    $apptCounts->get((string) $client->id, 0),
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Enriches a client using pre-loaded batch appointments — zero extra queries
    private function enrichFromBatch(Client $client, \Illuminate\Support\Collection $allApptsByClient, Carbon $now): array
    {
        $appts      = $allApptsByClient->get((string) $client->id, collect());
        $totalSpent = (float) $appts->where('estado', 'completada')
            ->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));
        $lastAppt   = $appts->sortByDesc(fn ($a) => (string) $a->fecha)->first();
        $lastFecha  = $lastAppt?->fecha;
        $lastDate   = $lastFecha
            ? (is_string($lastFecha) ? substr($lastFecha, 0, 10) : Carbon::parse($lastFecha)->toDateString())
            : null;

        return [
            'id'                => $client->id,
            'slug'              => $client->slug,
            'name'              => $client->user?->name,
            'email'             => $client->user?->email,
            'telefono'          => $client->telefono,
            'segment'           => $this->computeSegment($client, $appts->count(), $lastFecha),
            'totalAppointments' => $appts->count(),
            'totalSpent'        => $totalSpent,
            'lastAppointment'   => $lastDate,
            'joinedAt'          => optional($client->created_at)->toIso8601String(),
        ];
    }

    // Pure computation — no DB queries
    private function computeSegment(Client $client, int $apptCount, mixed $lastFecha): string
    {
        if ($apptCount > 10) {
            return 'vip';
        }

        $daysSinceJoin = (int) optional($client->created_at)->diffInDays(Carbon::now());
        if ($daysSinceJoin <= 14) {
            return 'new';
        }

        $daysSinceLast = $lastFecha
            ? (int) Carbon::parse($lastFecha)->diffInDays(Carbon::now())
            : 999;

        return $daysSinceLast > 30 ? 'inactive' : 'active';
    }
}
