<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Controlador de administración de clientes (panel admin).
 * Expone listado con filtros/paginación, detalle con historial de citas,
 * segmentación (vip/new/active/inactive), alta/edición/baja y exportación CSV.
 */
class ClientAdminController
{
    // El segmento (activo/inactivo/leal) se calcula en PHP a partir del
    // historial de citas, no es un campo guardado — no se puede paginar en
    // Mongo cuando se filtra por segmento sin antes calcularlo. Este limite
    // evita un escaneo sin fondo en ese caso; el path normal (sin segmento)
    // sí pagina de verdad a nivel de base de datos.
    private const SEGMENT_FILTER_SCAN_LIMIT = 1000;

    // Defensa en profundidad: aunque la ruta ya exige role.custom:administrador,
    // este guard evita que un descuido en routes/api.php exponga datos de clientes.
    private function authorizeAdmin(): void
    {
        abort_if(! request()->user()?->hasRole('administrador'), 403, 'Solo administradores pueden acceder a este recurso.');
    }

    // Lista clientes con búsqueda y paginación; si se filtra por segmento, pagina en memoria (ver constante arriba)
    public function getClients(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $search = $request->query('search', '');
        $segment = $request->query('segment');
        $perPage = min((int) $request->query('per_page', 15), 50);

        $query = Client::with('user');
        if ($search) {
            $query->whereHas('user', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
            );
        }

        if ($segment) {
            $clients = $query->limit(self::SEGMENT_FILTER_SCAN_LIMIT)->get();
        } else {
            $paginator = $query->paginate($perPage);
            $clients = collect($paginator->items());
        }

        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query for all appointments of all clients (was N queries via enrichClientData)
        $allAppts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id', 'estado', 'precio_cobrado', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        $now = Carbon::now();
        $enriched = $clients->map(fn ($c) => $this->enrichFromBatch($c, $allAppts, $now));

        if ($segment) {
            $enriched = $enriched->filter(fn ($c) => $c['segment'] === $segment)->values();

            return response()->json([
                'success' => true,
                'data' => $enriched,
                'total' => $enriched->count(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $enriched,
            'total' => $paginator->total(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    // Devuelve el perfil completo de un cliente: historial de citas, gasto total y barbero preferido
    public function show(Client $client): JsonResponse
    {
        $this->authorizeAdmin();
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
        $grouped = $appointments->groupBy(fn ($a) => (string) $a->barber_id)->map->count()->sortDesc();
        $topBarberId = $grouped->keys()->first();
        if ($topBarberId) {
            $topAppt = $appointments->first(fn ($a) => (string) $a->barber_id === $topBarberId);
            $preferredBarber = $topAppt?->barber?->user?->name ?? 'N/A';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'slug' => $client->slug,
                'name' => $client->user?->name,
                'email' => $client->user?->email,
                'telefono' => $client->telefono,
                'segment' => $segment,
                'joinedAt' => optional($client->created_at)->toIso8601String(),
                'totalAppointments' => $appointments->count(),
                'totalSpent' => $totalSpent,
                'averageSpent' => $appointments->count() > 0 ? round($totalSpent / $appointments->count(), 2) : 0,
                'lastAppointment' => optional($appointments->first()?->fecha)->toDateString(),
                'daysSinceLastAppointment' => $appointments->isNotEmpty()
                    ? optional($appointments->first()->fecha)->diffInDays(Carbon::now())
                    : null,
                'preferredBarber' => $preferredBarber,
                'appointments' => $appointments->map(fn ($a) => [
                    'id' => $a->id,
                    'code' => $a->code,
                    'fecha' => optional($a->fecha)->toDateString(),
                    'hora_inicio' => $a->hora_inicio,
                    'barber' => $a->barber?->user?->name,
                    'service' => $a->service?->nombre,
                    'precio' => $a->precio_cobrado,
                    'estado' => $a->estado,
                ]),
            ],
        ]);
    }

    // Cuenta clientes por segmento (vip/new/active/inactive) para el dashboard de segmentación
    public function getSegmentation(): JsonResponse
    {
        $this->authorizeAdmin();
        $clients = Client::with('user')->get();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query instead of 2N queries
        $allAppts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id', 'fecha'])
            ->groupBy(fn ($a) => (string) $a->client_id);

        $now = Carbon::now();
        $segments = ['vip' => 0, 'new' => 0, 'inactive' => 0, 'active' => 0];

        foreach ($clients as $client) {
            $appts = $allAppts->get((string) $client->id, collect());
            $lastFecha = $appts->sortByDesc(fn ($a) => (string) $a->fecha)->first()?->fecha;
            $seg = $this->computeSegment($client, $appts->count(), $lastFecha);
            $segments[$seg] = ($segments[$seg] ?? 0) + 1;
        }

        $total = $clients->count();

        return response()->json([
            'success' => true,
            'data' => collect($segments)->map(fn ($count, $key) => [
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
            ]),
        ]);
    }

    // Crea el User (con rol cliente) y su perfil Client asociado
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'telefono' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
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
            'data' => $enriched,
        ], 201);
    }

    // Actualiza datos del cliente y, opcionalmente, del usuario asociado (nombre/email)
    public function update(Client $client, Request $request): JsonResponse
    {
        $this->authorizeAdmin();
        $client->load('user');

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
        ]);

        if (! empty($validated['name']) || ! empty($validated['email'])) {
            $client->user?->update(array_filter([
                'name' => $validated['name'] ?? null,
                'email' => $validated['email'] ?? null,
            ]));
        }

        if (isset($validated['telefono'])) {
            $client->update(['telefono' => $validated['telefono']]);
        }

        $client->refresh()->load('user');
        $appts = Appointment::where('client_id', (string) $client->id)
            ->get(['client_id', 'estado', 'precio_cobrado', 'fecha']);
        $grouped = collect([(string) $client->id => $appts]);
        $enriched = $this->enrichFromBatch($client, $grouped, Carbon::now());

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente',
            'data' => $enriched,
        ]);
    }

    // Elimina el cliente y su usuario asociado (solo si no tiene citas registradas)
    public function destroy(Client $client): JsonResponse
    {
        $this->authorizeAdmin();
        // Bloquear el borrado si el cliente tiene citas: son registros
        // historicos/financieros que apuntan a client_id y quedarian huerfanos.
        if ($client->appointments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar un cliente con citas registradas.',
            ], 422);
        }

        DB::transaction(function () use ($client): void {
            $client->user?->delete();
            $client->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    // Exporta el listado completo de clientes a CSV en streaming (evita cargar todo en memoria de golpe)
    public function export(Request $request)
    {
        $this->authorizeAdmin();
        $clients = Client::with('user')->get();
        $clientIds = $clients->pluck('id')->map(fn ($id) => (string) $id)->all();

        // 1 batch query for all appointment counts (was N queries)
        $apptCounts = Appointment::whereIn('client_id', $clientIds)
            ->get(['client_id'])
            ->groupBy(fn ($a) => (string) $a->client_id)
            ->map(fn ($g) => $g->count());

        $filename = 'clients_'.date('Y-m-d').'.csv';

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
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // Enriquece un cliente usando citas precargadas en lote — cero queries extra
    private function enrichFromBatch(Client $client, Collection $allApptsByClient, Carbon $now): array
    {
        $appts = $allApptsByClient->get((string) $client->id, collect());
        $totalSpent = (float) $appts->where('estado', 'completada')
            ->sum(fn ($a) => (float) ($a->precio_cobrado ?? 0));
        $lastAppt = $appts->sortByDesc(fn ($a) => (string) $a->fecha)->first();
        $lastFecha = $lastAppt?->fecha;
        $lastDate = $lastFecha
            ? (is_string($lastFecha) ? substr($lastFecha, 0, 10) : Carbon::parse($lastFecha)->toDateString())
            : null;

        return [
            'id' => $client->id,
            'slug' => $client->slug,
            'name' => $client->user?->name,
            'email' => $client->user?->email,
            'telefono' => $client->telefono,
            'segment' => $this->computeSegment($client, $appts->count(), $lastFecha),
            'totalAppointments' => $appts->count(),
            'totalSpent' => $totalSpent,
            'lastAppointment' => $lastDate,
            'joinedAt' => optional($client->created_at)->toIso8601String(),
        ];
    }

    // Cálculo puro en memoria — sin queries a la base de datos
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
