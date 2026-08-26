<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\Sortable;
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

/**
 * Administración de clientes (admin/recepción): listado con filtros y
 * estadísticas, ficha detallada, alta manual, edición y baja (bloqueada si
 * el cliente tiene historial de citas).
 */
class ClientController extends Controller
{
    use Sortable;

    // Listado de clientes con filtros de búsqueda/citas/fecha de alta y estadísticas agregadas.
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'sin_citas', 'fecha_desde', 'fecha_hasta']);
        $search = trim((string) ($filters['q'] ?? ''));

        // has()/doesntHave() sobre la relacion appointments son extremadamente lentos en
        // MongoDB (~90s con 112k citas: laravel-mongodb los resuelve con un exists-check
        // por cada cliente, no con un JOIN real). En su lugar, se saca el set de client_id
        // con al menos una cita via distinct() nativo de Mongo (~2s) y se compara en PHP.
        $clientIdsWithAppointments = collect(
            Appointment::raw(fn ($collection) => $collection->distinct('client_id'))
        )->filter()->map(fn ($id) => (string) $id)->values();

        $query = Client::query()
            ->with('user:id,name,email')
            ->when($search !== '', fn ($query) => $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(! empty($filters['sin_citas']), fn ($q) => $q->whereNotIn('_id', $clientIdsWithAppointments))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereHas('user', fn ($u) => $u->whereDate('created_at', '>=', $filters['fecha_desde'])))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereHas('user', fn ($u) => $u->whereDate('created_at', '<=', $filters['fecha_hasta'])));

        // Columnas ordenables de este listado. Nombre/email viven en el
        // usuario relacionado (no se puede ordenar por ahi sin agregacion
        // en MongoDB), asi que solo se exponen columnas propias del
        // documento Client:
        //   - telefono         -> alfanumerico (como texto: "55..." antes que "56...")
        //   - fecha_nacimiento -> cronologico (mas joven / mas grande primero)
        //   - nivel            -> alfabetico (nuevo, regular, vip, leyenda)
        //   - puntos           -> numerico (puntos de lealtad acumulados)
        //   - total_citas      -> numerico (citas totales registradas en el cliente)
        //   - id               -> orden de alta (antiguedad de registro), es el default
        $clients = $this->applySort(
            $query,
            $request,
            ['telefono', 'fecha_nacimiento', 'nivel', 'puntos', 'total_citas', 'id'],
            'id',
            'desc'
        )
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

        $totalClients = Client::count();
        $conCitas = $clientIdsWithAppointments->count();

        $stats = [
            'total' => $totalClients,
            'con_citas' => $conCitas,
            'sin_citas' => $totalClients - $conCitas,
            'este_mes' => Client::whereHas('user', fn ($u) => $u->whereMonth('created_at', now()->month))->count(),
        ];

        return view('clients.index', compact('clients', 'filters', 'search', 'stats'));
    }

    // Ficha del cliente con estadísticas de gasto/citas y barbero/servicio favorito (calculados en PHP).
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

    // Crea el usuario + perfil de cliente en una transacción; genera password aleatoria si no se envía una.
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

    // Actualiza datos de usuario (nombre/email) y perfil de cliente (teléfono, preferencias, etc.).
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
