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
use App\Services\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(): View
    {
        $appointments = Appointment::query()
            ->with(['client.user', 'barber.user', 'service'])
            ->latest('fecha')
            ->latest('hora_inicio')
            ->paginate(15);

        return view('appointments.index', compact('appointments'));
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
