<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\User;
use App\Models\Appointment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class ClientAdminController extends Controller
{
    /**
     * Obtener lista de clientes con segmentación
     */
    public function getClients(Request $request)
    {
        $segment = $request->query('segment', null);
        $search = $request->query('search', '');

        $query = User::whereHas('roles', function ($q) {
            $q->where('name', 'cliente');
        });

        // Aplicar filtro de búsqueda
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->get()->map(function ($client) {
            return $this->enrichClientData($client);
        });

        // Aplicar filtro de segmento
        if ($segment) {
            $clients = $clients->filter(function ($client) use ($segment) {
                return $client['segment'] === $segment;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $clients,
            'total' => $clients->count(),
        ]);
    }

    /**
     * Obtener detalles completos de un cliente
     */
    public function show($clientId)
    {
        $client = User::findOrFail($clientId);

        $appointments = Appointment::where('client_id', $clientId)
            ->with('barber')
            ->orderBy('date', 'desc')
            ->get();

        $segment = $this->getClientSegment($client);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'photo' => $client->photo_url,
                'segment' => $segment,
                'joinedAt' => $client->created_at,
                'totalAppointments' => $appointments->count(),
                'totalSpent' => $appointments->sum('price'),
                'averageSpent' => $appointments->count() > 0 ? $appointments->sum('price') / $appointments->count() : 0,
                'lastAppointment' => $appointments->first()?->date,
                'daysSinceLastAppointment' => $appointments->first() ? $appointments->first()->date->diffInDays(Carbon::now()) : null,
                'preferredBarber' => $this->getPreferredBarber($clientId),
                'appointments' => $appointments->map(function ($apt) {
                    return [
                        'id' => $apt->id,
                        'date' => $apt->date,
                        'time' => $apt->time,
                        'barber' => $apt->barber?->name,
                        'service' => $apt->service_id,
                        'price' => $apt->price,
                        'status' => $apt->status,
                    ];
                }),
                'preferences' => [
                    'preferredTime' => $this->getPreferredTime($clientId),
                    'preferredService' => 'Corte + Barba',
                    'notes' => 'Cliente VIP - Requiere atención especial',
                ],
            ],
        ]);
    }

    /**
     * Obtener segmentación de clientes
     */
    public function getSegmentation()
    {
        $allClients = User::whereHas('roles', function ($q) {
            $q->where('name', 'cliente');
        })->get();

        $segments = [
            'vip' => ['count' => 0, 'clients' => []],
            'new' => ['count' => 0, 'clients' => []],
            'inactive' => ['count' => 0, 'clients' => []],
            'debtors' => ['count' => 0, 'clients' => []],
        ];

        foreach ($allClients as $client) {
            $segment = $this->getClientSegment($client);
            $segments[$segment]['count']++;
            $segments[$segment]['clients'][] = $client->id;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vip' => [
                    'count' => $segments['vip']['count'],
                    'percentage' => $allClients->count() > 0 ? $segments['vip']['count'] / $allClients->count() : 0,
                    'avgSpent' => $this->getSegmentAvgSpent('vip'),
                    'rating' => 4.8,
                ],
                'new' => [
                    'count' => $segments['new']['count'],
                    'percentage' => $allClients->count() > 0 ? $segments['new']['count'] / $allClients->count() : 0,
                    'avgSpent' => $this->getSegmentAvgSpent('new'),
                    'avgAppointments' => 2,
                ],
                'inactive' => [
                    'count' => $segments['inactive']['count'],
                    'percentage' => $allClients->count() > 0 ? $segments['inactive']['count'] / $allClients->count() : 0,
                    'avgDaysSinceAppointment' => 45,
                    'totalSpent' => $this->getSegmentTotalSpent('inactive'),
                ],
                'debtors' => [
                    'count' => $segments['debtors']['count'],
                    'percentage' => $allClients->count() > 0 ? $segments['debtors']['count'] / $allClients->count() : 0,
                    'totalDebt' => 450,
                    'avgDebt' => 90,
                ],
            ],
        ]);
    }

    /**
     * Crear nuevo cliente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        $client = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => bcrypt($validated['password']),
        ]);

        $client->assignRole('cliente');

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado correctamente',
            'data' => $this->enrichClientData($client),
        ], 201);
    }

    /**
     * Actualizar cliente
     */
    public function update($clientId, Request $request)
    {
        $client = User::findOrFail($clientId);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $clientId,
            'phone' => 'string|max:20',
            'preferences' => 'array|nullable',
        ]);

        $client->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado correctamente',
            'data' => $this->enrichClientData($client),
        ]);
    }

    /**
     * Eliminar cliente
     */
    public function destroy($clientId)
    {
        $client = User::findOrFail($clientId);
        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado correctamente',
        ]);
    }

    /**
     * Exportar clientes a CSV
     */
    public function export(Request $request)
    {
        $format = $request->query('format', 'csv');
        $segment = $request->query('segment', null);

        $clients = User::whereHas('roles', function ($q) {
            $q->where('name', 'cliente');
        })->get();

        if ($format === 'csv') {
            return $this->exportCSV($clients);
        } elseif ($format === 'pdf') {
            return $this->exportPDF($clients);
        }

        return response()->json(['error' => 'Formato no soportado'], 400);
    }

    /**
     * Enriquecer datos del cliente con información de citas
     */
    private function enrichClientData($client)
    {
        $appointments = Appointment::where('client_id', $client->id)->get();
        $segment = $this->getClientSegment($client);

        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'segment' => $segment,
            'totalAppointments' => $appointments->count(),
            'totalSpent' => $appointments->sum('price'),
            'lastAppointment' => $appointments->sortByDesc('date')->first()?->date,
            'joinedAt' => $client->created_at,
        ];
    }

    /**
     * Determinar segmento del cliente
     */
    private function getClientSegment($client)
    {
        $appointmentCount = Appointment::where('client_id', $client->id)->count();
        $daysSinceJoin = $client->created_at->diffInDays(Carbon::now());
        $daysSinceLastAppointment = Appointment::where('client_id', $client->id)
            ->orderBy('date', 'desc')
            ->first()?->date?->diffInDays(Carbon::now()) ?? 999;

        // VIP: Más de 10 citas
        if ($appointmentCount > 10) {
            return 'vip';
        }

        // Nuevos: Registrados hace menos de 2 semanas
        if ($daysSinceJoin <= 14) {
            return 'new';
        }

        // Inactivos: Sin citas hace más de 30 días
        if ($daysSinceLastAppointment > 30) {
            return 'inactive';
        }

        // Deudores: Tienen pagos pendientes (requiere tabla payments)
        // Por ahora: default activo
        return 'active';
    }

    /**
     * Obtener barbero preferido del cliente
     */
    private function getPreferredBarber($clientId)
    {
        $preferredBarber = Appointment::where('client_id', $clientId)
            ->select('barber_id')
            ->groupBy('barber_id')
            ->orderByRaw('COUNT(*) DESC')
            ->with('barber')
            ->first();

        return $preferredBarber?->barber?->name ?? 'N/A';
    }

    /**
     * Obtener hora preferida del cliente
     */
    private function getPreferredTime($clientId)
    {
        return Appointment::where('client_id', $clientId)
            ->select('time')
            ->groupBy('time')
            ->orderByRaw('COUNT(*) DESC')
            ->first()?->time ?? 'Flexible';
    }

    /**
     * Obtener gasto promedio por segmento
     */
    private function getSegmentAvgSpent($segment)
    {
        // Demo
        return match ($segment) {
            'vip' => 450,
            'new' => 90,
            default => 200,
        };
    }

    /**
     * Obtener gasto total por segmento
     */
    private function getSegmentTotalSpent($segment)
    {
        // Demo
        return 1200;
    }

    /**
     * Exportar a CSV
     */
    private function exportCSV($clients)
    {
        $filename = 'clients_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($clients) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nombre', 'Email', 'Teléfono', 'Total Citas', 'Total Gastado']);

            foreach ($clients as $client) {
                $appointments = Appointment::where('client_id', $client->id)->get();
                fputcsv($handle, [
                    $client->id,
                    $client->name,
                    $client->email,
                    $client->phone,
                    $appointments->count(),
                    $appointments->sum('price'),
                ]);
            }

            fclose($handle);
        }, 200);
    }

    /**
     * Exportar a PDF
     */
    private function exportPDF($clients)
    {
        // Implementar con DomPDF o mPDF
        return response()->json(['message' => 'PDF export coming soon']);
    }
}
