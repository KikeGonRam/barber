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

        $clients = $query->get()->map(fn ($c) => $this->enrichClientData($c));

        if ($segment) {
            $clients = $clients->filter(fn ($c) => $c['segment'] === $segment)->values();
        }

        return response()->json([
            'success' => true,
            'data'    => $clients,
            'total'   => $clients->count(),
        ]);
    }

    public function show($clientId): JsonResponse
    {
        $client = Client::with('user')->findOrFail($clientId);

        $appointments = Appointment::where('client_id', $clientId)
            ->with(['barber.user', 'service'])
            ->orderBy('fecha', 'desc')
            ->get();

        $totalSpent = (float) $appointments->where('estado', 'completada')->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                        => $client->id,
                'name'                      => $client->user?->name,
                'email'                     => $client->user?->email,
                'telefono'                  => $client->telefono,
                'segment'                   => $this->getClientSegment($client),
                'joinedAt'                  => optional($client->created_at)->toIso8601String(),
                'totalAppointments'         => $appointments->count(),
                'totalSpent'                => $totalSpent,
                'averageSpent'              => $appointments->count() > 0 ? round($totalSpent / $appointments->count(), 2) : 0,
                'lastAppointment'           => optional($appointments->first()?->fecha)->toDateString(),
                'daysSinceLastAppointment'  => $appointments->isNotEmpty()
                    ? optional($appointments->first()->fecha)->diffInDays(Carbon::now())
                    : null,
                'preferredBarber'           => $this->getPreferredBarber((string) $client->id),
                'appointments'              => $appointments->map(fn ($a) => [
                    'id'          => $a->id,
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
        $clients = Client::with('user')->get();

        $segments = ['vip' => 0, 'new' => 0, 'inactive' => 0, 'active' => 0];
        foreach ($clients as $c) {
            $seg = $this->getClientSegment($c);
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

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado correctamente',
            'data'    => $this->enrichClientData($client->load('user')),
        ], 201);
    }

    public function update($clientId, Request $request): JsonResponse
    {
        $client = Client::with('user')->findOrFail($clientId);

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

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente',
            'data'    => $this->enrichClientData($client->fresh('user')),
        ]);
    }

    public function destroy($clientId): JsonResponse
    {
        $client = Client::with('user')->findOrFail($clientId);
        $client->user?->delete();
        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    public function export(Request $request)
    {
        $clients  = Client::with('user')->get();
        $filename = 'clients_' . date('Y-m-d') . '.csv';

        return response()->stream(function () use ($clients) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nombre', 'Email', 'Teléfono', 'Total Citas']);

            foreach ($clients as $client) {
                $count = Appointment::where('client_id', $client->id)->count();
                fputcsv($handle, [
                    $client->id,
                    $client->user?->name,
                    $client->user?->email,
                    $client->telefono,
                    $count,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function enrichClientData(Client $client): array
    {
        $appointments = Appointment::where('client_id', $client->id)->get(['estado', 'precio_cobrado', 'fecha']);
        $totalSpent   = (float) $appointments->where('estado', 'completada')->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));

        return [
            'id'                => $client->id,
            'name'              => $client->user?->name,
            'email'             => $client->user?->email,
            'telefono'          => $client->telefono,
            'segment'           => $this->getClientSegment($client),
            'totalAppointments' => $appointments->count(),
            'totalSpent'        => $totalSpent,
            'lastAppointment'   => optional($appointments->sortByDesc('fecha')->first()?->fecha)->toDateString(),
            'joinedAt'          => optional($client->created_at)->toIso8601String(),
        ];
    }

    private function getClientSegment(Client $client): string
    {
        $count       = Appointment::where('client_id', $client->id)->count();
        $daysSinceJoin = (int) optional($client->created_at)->diffInDays(Carbon::now());

        if ($count > 10) return 'vip';
        if ($daysSinceJoin <= 14) return 'new';

        $lastFecha = Appointment::where('client_id', $client->id)
            ->orderBy('fecha', 'desc')
            ->value('fecha');

        $daysSinceLast = $lastFecha
            ? (int) Carbon::parse($lastFecha)->diffInDays(Carbon::now())
            : 999;

        if ($daysSinceLast > 30) return 'inactive';

        return 'active';
    }

    private function getPreferredBarber(string $clientId): string
    {
        $grouped = Appointment::where('client_id', $clientId)
            ->get(['barber_id'])
            ->groupBy('barber_id')
            ->map->count()
            ->sortDesc();

        $barberId = $grouped->keys()->first();
        if (! $barberId) return 'N/A';

        $barber = Barber::with('user')->find($barberId);
        return $barber?->user?->name ?? 'N/A';
    }
}
