<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientProfileRequest;
use App\Http\Requests\Client\UpdateClientProfileRequest;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
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
        $filters = $request->only(['q', 'sin_citas', 'fecha_desde', 'fecha_hasta']);
        $search = trim((string) ($filters['q'] ?? ''));

        $clients = Client::query()
            ->with('user:id,name,email')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(! empty($filters['sin_citas']), fn ($q) => $q->doesntHave('appointments'))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereHas('user', fn ($u) => $u->whereDate('created_at', '>=', $filters['fecha_desde'])))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereHas('user', fn ($u) => $u->whereDate('created_at', '<=', $filters['fecha_hasta'])))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        // withCount not supported by MongoDB — compute PHP-side after pagination
        $ids = $clients->pluck('id')->toArray();
        if (! empty($ids)) {
            $apptCounts = Appointment::whereIn('client_id', $ids)
                ->get(['client_id'])
                ->groupBy('client_id')
                ->map->count();
            $clients->each(fn ($c) => $c->appointments_count = $apptCounts->get($c->id, 0));
        }

        $stats = [
            'total' => Client::count(),
            'con_citas' => Client::has('appointments')->count(),
            'sin_citas' => Client::doesntHave('appointments')->count(),
            'este_mes' => Client::whereHas('user', fn ($u) => $u->whereMonth('created_at', now()->month))->count(),
        ];

        return view('clients.index', compact('clients', 'filters', 'search', 'stats'));
    }

    public function show(Client $client): View
    {
        $client->load(['user', 'appointments.service', 'appointments.barber.user', 'appointments.payments']);

        $stats = [
            'total_citas' => $client->appointments->count(),
            'completadas' => $client->appointments->where('estado', 'completada')->count(),
            'canceladas' => $client->appointments->where('estado', 'cancelada')->count(),
            'total_gastado' => $client->appointments->flatMap->payments->sum(fn ($p) => ($p->monto ?? 0) + ($p->propina ?? 0)),
            'barbero_favorito' => $client->appointments
                ->where('estado', 'completada')
                ->groupBy('barber_id')
                ->sortByDesc(fn ($g) => $g->count())
                ->first()?->first()?->barber?->user?->name ?? '—',
            'servicio_favorito' => $client->appointments
                ->where('estado', 'completada')
                ->groupBy('service_id')
                ->sortByDesc(fn ($g) => $g->count())
                ->first()?->first()?->service?->nombre ?? '—',
            'ultima_visita' => $client->appointments->where('estado', 'completada')->sortByDesc('fecha')->first()?->fecha,
        ];

        $recentAppointments = $client->appointments->sortByDesc('fecha')->take(10);

        return view('clients.show', compact('client', 'stats', 'recentAppointments'));
    }

    public function create(): View
    {
        return view('clients.create');
    }

    public function store(StoreClientProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make(($data['password'] ?? null) ?: Str::password(12)),
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

    public function destroy(Client $client): RedirectResponse
    {
        // El cliente se borra en duro (no usa SoftDeletes), pero sus citas y
        // pagos son registros historicos/financieros que apuntan a client_id.
        // Borrarlo con citas existentes las dejaria huerfanas en reportes y
        // facturas. Se bloquea, igual que UserController::destroy bloquea el
        // auto-borrado.
        if ($client->appointments()->exists()) {
            return redirect()->route('clients.index')->withErrors([
                'general' => 'No se puede eliminar un cliente con citas registradas. Su historial es parte de los reportes.',
            ]);
        }

        DB::transaction(function () use ($client): void {
            $client->user?->delete();
            $client->delete();
        });

        return redirect()->route('clients.index')->with('status', 'Cliente eliminado correctamente.');
    }
}
