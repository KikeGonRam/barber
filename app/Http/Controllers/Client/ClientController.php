<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientProfileRequest;
use App\Http\Requests\Client\UpdateClientProfileRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $clients = Client::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients', 'search'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(StoreClientProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = \App\Models\User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'] ?: Str::password(12)),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('cliente');

            Client::query()->create([
                'user_id' => $user->id,
                'telefono' => $data['telefono'] ?? null,
                'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                'preferencias_notificacion' => [
                    'in_app' => (bool) ($data['pref_in_app'] ?? true),
                    'email' => (bool) ($data['pref_email'] ?? true),
                    'sms' => (bool) ($data['pref_sms'] ?? false),
                    'whatsapp' => (bool) ($data['pref_whatsapp'] ?? false),
                ],
            ]);
        });

        return redirect()->route('clients.index')->with('status', 'Cliente creado correctamente.');
    }

    public function edit(Client $client): View
    {
        $client->load('user:id,name,email');

        return view('clients.edit', compact('client'));
    }

    public function update(UpdateClientProfileRequest $request, Client $client): RedirectResponse
    {
        $data = $request->validated();

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

        return redirect()->route('clients.index')->with('status', 'Cliente actualizado correctamente.');
    }
}
