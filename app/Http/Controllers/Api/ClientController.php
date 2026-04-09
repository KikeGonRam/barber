<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'pref_in_app' => ['nullable', 'boolean'],
            'pref_email' => ['nullable', 'boolean'],
            'pref_sms' => ['nullable', 'boolean'],
            'pref_whatsapp' => ['nullable', 'boolean'],
        ]);

        $client = DB::transaction(function () use ($data): Client {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?? Str::password(12)),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('cliente');

            return Client::query()->create([
                'user_id' => $user->id,
                'telefono' => $data['telefono'] ?? null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'preferencias_notificacion' => [
                    'in_app' => (bool) ($data['pref_in_app'] ?? true),
                    'email' => (bool) ($data['pref_email'] ?? true),
                    'sms' => (bool) ($data['pref_sms'] ?? false),
                    'whatsapp' => (bool) ($data['pref_whatsapp'] ?? false),
                ],
            ])->fresh('user:id,name,email');
        });

        return response()->json([
            'message' => 'Cliente creado correctamente.',
            'data' => [
                'id' => $client->id,
                'user' => [
                    'id' => $client->user?->id,
                    'name' => $client->user?->name,
                    'email' => $client->user?->email,
                ],
                'telefono' => $client->telefono,
                'fecha_nacimiento' => optional($client->fecha_nacimiento)?->toDateString(),
                'preferencias_notificacion' => $client->preferencias_notificacion ?? [],
            ],
        ], 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$client->user_id],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'pref_in_app' => ['nullable', 'boolean'],
            'pref_email' => ['nullable', 'boolean'],
            'pref_sms' => ['nullable', 'boolean'],
            'pref_whatsapp' => ['nullable', 'boolean'],
        ]);

        $client->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $client->update([
            'telefono' => $data['telefono'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'preferencias_notificacion' => [
                'in_app' => (bool) ($data['pref_in_app'] ?? false),
                'email' => (bool) ($data['pref_email'] ?? false),
                'sms' => (bool) ($data['pref_sms'] ?? false),
                'whatsapp' => (bool) ($data['pref_whatsapp'] ?? false),
            ],
        ]);

        $client->load('user:id,name,email');

        return response()->json([
            'message' => 'Cliente actualizado correctamente.',
            'data' => [
                'id' => $client->id,
                'user' => [
                    'id' => $client->user?->id,
                    'name' => $client->user?->name,
                    'email' => $client->user?->email,
                ],
                'telefono' => $client->telefono,
                'fecha_nacimiento' => optional($client->fecha_nacimiento)?->toDateString(),
                'preferencias_notificacion' => $client->preferencias_notificacion ?? [],
            ],
        ]);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->authorizeStaff($request);

        DB::transaction(function () use ($client): void {
            $user = $client->user;
            $client->delete();

            if ($user) {
                $user->delete();
            }
        });

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}