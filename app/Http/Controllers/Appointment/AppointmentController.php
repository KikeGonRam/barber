<?php

namespace App\Http\Controllers\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentNotifier $notifier,
        private readonly AppointmentStatusService $statusService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'estado', 'barber_id', 'fecha_desde', 'fecha_hasta']);

        $appointments = Appointment::query()
            ->with(['client.user', 'barber.user', 'service'])
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->whereHas('client.user', fn ($u) => $u->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('service', fn ($s) => $s->where('nombre', 'like', "%{$q}%"))
                    ->orWhereHas('barber.user', fn ($b) => $b->where('name', 'like', "%{$q}%"));
            })
            ->when(! empty($filters['estado']), fn ($q) => $q->where('estado', $filters['estado']))
            ->when(! empty($filters['barber_id']), fn ($q) => $q->where('barber_id', $filters['barber_id']))
            ->when(! empty($filters['fecha_desde']), fn ($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(! empty($filters['fecha_hasta']), fn ($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->latest('fecha')
            ->latest('hora_inicio')
            ->paginate(20)
            ->withQueryString();

        $barbers = Barber::with('user:id,name')->where('activo', true)->get(['id', 'user_id']);
        $services = Service::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'duracion_min', 'precio']);
        $stats = [
            'total' => Appointment::count(),
            'today' => Appointment::whereDate('fecha', today())->count(),
            'pendiente' => Appointment::where('estado', 'pendiente')->count(),
            'completada' => Appointment::where('estado', 'completada')->count(),
        ];

        return view('appointments.index', compact('appointments', 'filters', 'barbers', 'services', 'stats'));
    }

    /**
     * Búsqueda de clientes para el modal walk-in: por nombre o teléfono,
     * máximo 10 resultados. El cliente parado en recepción normalmente da
     * su teléfono, por eso se busca en ambos campos.
     */
    public function searchClients(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['clients' => []]);
        }

        $byPhone = Client::query()
            ->where('telefono', 'like', "%{$q}%")
            ->with('user:id,name')
            ->limit(10)
            ->get(['id', 'user_id', 'telefono']);

        $byName = Client::query()
            ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%"))
            ->with('user:id,name')
            ->limit(10)
            ->get(['id', 'user_id', 'telefono']);

        $clients = $byPhone->concat($byName)
            ->unique(fn ($c) => (string) $c->id)
            ->take(10)
            ->map(fn ($c) => [
                'id' => (string) $c->id,
                'name' => $c->user?->name ?? 'Cliente',
                'telefono' => $c->telefono,
            ])
            ->values();

        return response()->json(['clients' => $clients]);
    }

    /**
     * Registro rápido de walk-in: cliente parado en recepción, sin cita previa.
     * Crea la cita para AHORA MISMO en estado en_proceso, con la hora de fin
     * calculada a partir de la duración del servicio. Reutiliza
     * createAppointment(), que valida solapamientos con la agenda del barbero.
     */
    public function walkIn(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'string', 'exists:clients,id'],
            'barber_id' => ['required', 'string', 'exists:barbers,id'],
            'service_id' => ['required', 'string', 'exists:services,id'],
        ], [
            'client_id.required' => 'Selecciona el cliente.',
            'barber_id.required' => 'Selecciona el barbero.',
            'service_id.required' => 'Selecciona el servicio.',
        ]);

        $service = Service::findOrFail($data['service_id']);
        $inicio = now();
        $fin = $inicio->copy()->addMinutes((int) $service->duracion_min);

        try {
            $this->appointmentService->createAppointment([
                'client_id' => $data['client_id'],
                'barber_id' => $data['barber_id'],
                'service_id' => $data['service_id'],
                'fecha' => $inicio->format('Y-m-d'),
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fin' => $fin->format('H:i'),
                'estado' => 'en_proceso',
                'precio_cobrado' => (float) $service->precio,
                'metodo_pago' => 'efectivo',
                'notas' => 'Walk-in registrado en recepción.',
                'servicio_iniciado_en' => $inicio,
            ]);
        } catch (AppointmentConflictException $exception) {
            return back()->withErrors(['walkin' => $exception->getMessage()]);
        }

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Walk-in registrado: el servicio ya está en proceso.');
    }

    public function create(): View
    {
        $clients = Client::query()->with('user:id,name')->get(['id', 'user_id'])->sortBy(fn (Client $client) => strtolower((string) $client->user?->name))->values();
        $barbers = Barber::query()->with('user:id,name')->where('activo', true)->get(['id', 'user_id'])->sortBy(fn (Barber $barber) => strtolower((string) $barber->user?->name))->values();
        $services = Service::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'duracion_min', 'precio']);

        return view('appointments.create', compact('clients', 'barbers', 'services'));
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        try {
            $this->appointmentService->createAppointment($request->validated());
        } catch (AppointmentConflictException $exception) {
            return back()
                ->withInput()
                ->withErrors(['hora_inicio' => $exception->getMessage()]);
        }

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Cita creada correctamente.');
    }

    public function edit(Appointment $appointment): View
    {
        $clients = Client::query()->with('user:id,name')->get(['id', 'user_id'])->sortBy(fn (Client $client) => strtolower((string) $client->user?->name))->values();
        $barbers = Barber::query()->with('user:id,name')->get(['id', 'user_id'])->sortBy(fn (Barber $barber) => strtolower((string) $barber->user?->name))->values();
        $services = Service::query()->orderBy('nombre')->get(['id', 'nombre', 'duracion_min', 'precio']);

        return view('appointments.edit', compact('appointment', 'clients', 'barbers', 'services'));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        try {
            $this->appointmentService->updateAppointment($appointment->id, $request->validated());
        } catch (AppointmentConflictException $exception) {
            return back()
                ->withInput()
                ->withErrors(['hora_inicio' => $exception->getMessage()]);
        }

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Cita actualizada correctamente.');
    }

    public function calendar(): View
    {
        $barbers = Barber::with('user:id,name')->where('activo', true)->get(['id', 'user_id']);

        return view('appointments.calendar', compact('barbers'));
    }

    public function calendarData(Request $request): JsonResponse
    {
        $start = $request->query('start');
        $end = $request->query('end');
        $barberId = $request->query('barber_id');

        $appointments = Appointment::with(['client.user:id,name', 'service:id,nombre', 'barber.user:id,name'])
            ->when($start, fn ($q) => $q->whereDate('fecha', '>=', $start))
            ->when($end, fn ($q) => $q->whereDate('fecha', '<=', $end))
            ->when($barberId, fn ($q) => $q->where('barber_id', $barberId))
            ->get();

        $statusColors = [
            'pendiente' => '#d97706',
            'confirmada' => '#3b82f6',
            'en_proceso' => '#06b6d4',
            'completada' => '#10b981',
            'cancelada' => '#ef4444',
            'no_asistio' => '#6b7280',
        ];

        $events = $appointments->map(function (Appointment $appt) use ($statusColors) {
            $color = $statusColors[$appt->estado] ?? '#d4af37';

            return [
                'id' => $appt->id,
                'title' => ($appt->client?->user?->name ?? 'Cliente').' — '.($appt->service?->nombre ?? ''),
                'start' => $appt->fecha->format('Y-m-d').'T'.$appt->hora_inicio,
                'end' => $appt->fecha->format('Y-m-d').'T'.($appt->hora_fin ?? $appt->hora_inicio),
                'color' => $color,
                'textColor' => '#fff',
                'extendedProps' => [
                    'cliente' => $appt->client?->user?->name ?? '—',
                    'servicio' => $appt->service?->nombre ?? '—',
                    'barbero' => $appt->barber?->user?->name ?? '—',
                    'estado' => $appt->estado,
                    'edit_url' => route('appointments.edit', $appt->id),
                ],
            ];
        });

        return response()->json($events);
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $estado = (string) $request->input('estado');
        $wasCompletada = $appointment->estado === 'completada';

        // Transicion validada por la maquina de estados (flujo estricto).
        try {
            $this->statusService->transition($appointment, $estado);
        } catch (InvalidAppointmentTransitionException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }

        if ($estado === 'completada' && ! $wasCompletada) {
            $client = $appointment->client ?? Client::find($appointment->client_id);
            if ($client) {
                app(LoyaltyService::class)->awardCitaPoints($client, (string) $appointment->id);
            }
        }

        $this->notifier->statusChanged($appointment, $estado);

        return back()->with('status', "Cita actualizada a: {$estado}.");
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        // No se puede cancelar una cita ya completada/cancelada/no asistida.
        try {
            $this->statusService->transition($appointment, 'cancelada');
        } catch (InvalidAppointmentTransitionException $e) {
            return back()->withErrors(['estado' => $e->getMessage()]);
        }

        $this->notifier->cancelled($appointment);

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Cita cancelada correctamente.');
    }
}
