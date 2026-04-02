<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'Solo administradores y recepcionistas pueden consultar clientes.');

        $search = trim((string) $request->query('q', ''));

        $clients = Client::query()
            ->with('user:id,name,email')
            ->withCount('appointments')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $clients->getCollection()->map(fn (Client $client) => [
                'id' => $client->id,
                'telefono' => $client->telefono,
                'fecha_nacimiento' => optional($client->fecha_nacimiento)?->toDateString(),
                'created_at' => optional($client->created_at)?->toAtomString(),
                'appointments_count' => $client->appointments_count,
                'preferencias_notificacion' => $client->preferencias_notificacion ?? [],
                'user' => [
                    'id' => $client->user?->id,
                    'name' => $client->user?->name,
                    'email' => $client->user?->email,
                ],
            ])->values(),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
            'filters' => [
                'q' => $search,
            ],
        ]);
    }
}