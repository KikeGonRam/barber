<?php

namespace App\Http\Controllers\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Notifications\AppointmentNotification;
use App\Services\Appointment\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(\Illuminate\Http\Request $request): View
    {
        $filters = $request->only(['q', 'estado', 'barber_id', 'fecha_desde', 'fecha_hasta']);

        $appointments = Appointment::query()
            ->with(['client.user', 'barber.user', 'service'])
            ->when(!empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->whereHas('client.user', fn($u) => $u->where('name', 'like', "%{$q}%"))
                      ->orWhereHas('service', fn($s) => $s->where('nombre', 'like', "%{$q}%"))
                      ->orWhereHas('barber.user', fn($b) => $b->where('name', 'like', "%{$q}%"));
            })
            ->when(!empty($filters['estado']), fn($q) => $q->where('estado', $filters['estado']))
            ->when(!empty($filters['barber_id']), fn($q) => $q->where('barber_id', $filters['barber_id']))
            ->when(!empty($filters['fecha_desde']), fn($q) => $q->whereDate('fecha', '>=', $filters['fecha_desde']))
            ->when(!empty($filters['fecha_hasta']), fn($q) => $q->whereDate('fecha', '<=', $filters['fecha_hasta']))
            ->latest('fecha')
            ->latest('hora_inicio')
            ->paginate(20)
            ->withQueryString();

        $barbers = Barber::with('user:id,name')->where('activo', true)->get(['id', 'user_id']);
        $stats = [
            'total'      => Appointment::count(),
            'today'      => Appointment::whereDate('fecha', today())->count(),
            'pendiente'  => Appointment::where('estado', 'pendiente')->count(),
            'completada' => Appointment::where('estado', 'completada')->count(),
        ];

        return view('appointments.index', compact('appointments', 'filters', 'barbers', 'stats'));
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

    public function calendar(): \Illuminate\View\View
    {
        $barbers = Barber::with('user:id,name')->where('activo', true)->get(['id', 'user_id']);
        return view('appointments.calendar', compact('barbers'));
    }

    public function calendarData(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        $start    = $request->query('start');
        $end      = $request->query('end');
        $barberId = $request->query('barber_id');

        $appointments = Appointment::with(['client.user:id,name', 'service:id,nombre', 'barber.user:id,name'])
            ->when($start, fn($q) => $q->whereDate('fecha', '>=', $start))
            ->when($end,   fn($q) => $q->whereDate('fecha', '<=', $end))
            ->when($barberId, fn($q) => $q->where('barber_id', $barberId))
            ->get();

        $statusColors = [
            'pendiente'  => '#d97706',
            'confirmada' => '#3b82f6',
            'en_proceso' => '#06b6d4',
            'completada' => '#10b981',
            'cancelada'  => '#ef4444',
            'no_asistio' => '#6b7280',
        ];

        $events = $appointments->map(function (Appointment $appt) use ($statusColors) {
            $color = $statusColors[$appt->estado] ?? '#d4af37';
            return [
                'id'              => $appt->id,
                'title'           => ($appt->client?->user?->name ?? 'Cliente') . ' — ' . ($appt->service?->nombre ?? ''),
                'start'           => $appt->fecha->format('Y-m-d') . 'T' . $appt->hora_inicio,
                'end'             => $appt->fecha->format('Y-m-d') . 'T' . ($appt->hora_fin ?? $appt->hora_inicio),
                'color'           => $color,
                'textColor'       => '#fff',
                'extendedProps'   => [
                    'cliente'  => $appt->client?->user?->name ?? '—',
                    'servicio' => $appt->service?->nombre ?? '—',
                    'barbero'  => $appt->barber?->user?->name ?? '—',
                    'estado'   => $appt->estado,
                    'edit_url' => route('appointments.edit', $appt->id),
                ],
            ];
        });

        return response()->json($events);
    }

    public function updateStatus(\Illuminate\Http\Request $request, Appointment $appointment): RedirectResponse
    {
        $estado = $request->input('estado');
        $allowed = ['pendiente', 'confirmada', 'completada', 'cancelada', 'en_proceso', 'no_asistio'];

        if (!in_array($estado, $allowed)) {
            return back()->withErrors(['estado' => 'Estado no válido.']);
        }

        $appointment->update(['estado' => $estado]);

        return back()->with('status', "Cita actualizada a: {$estado}.");
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => now(),
        ]);

        $appointment->load(['client.user', 'service']);

        $user = $appointment->client?->user;

        if ($user) {
            $user->notify(new AppointmentNotification(
                appointment: $appointment,
                subject: 'Cita cancelada',
                title: 'Tu cita fue cancelada',
                message: 'Tu cita fue cancelada. Si deseas, puedes reagendar desde tu panel.',
            ));

            $appointment->update(['cancellation_notified_at' => now()]);
        }

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Cita cancelada correctamente.');
    }
}
