<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientAppointmentRequest;
use App\Http\Requests\Client\UpdateClientAppointmentRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Product;
use App\Models\Service;
use App\Services\Appointment\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientAppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(): View
    {
        $user = auth()->user();
        $client = $user->clientProfile;

        if (! $client && $user->hasRole('cliente')) {
            $client = $user->clientProfile()->create();
        }

        abort_if(! $client, 403);

        $clientId = (string) $client->id;
        $today    = now()->startOfDay();

        $stats = [
            'total'       => Appointment::where('client_id', $clientId)->count(),
            'proximas'    => Appointment::where('client_id', $clientId)
                                ->whereNotIn('estado', ['cancelada', 'completada'])
                                ->where('fecha', '>=', $today)->count(),
            'completadas' => Appointment::where('client_id', $clientId)->where('estado', 'completada')->count(),
            'canceladas'  => Appointment::where('client_id', $clientId)->where('estado', 'cancelada')->count(),
        ];

        $nextAppointment = Appointment::where('client_id', $clientId)
            ->whereNotIn('estado', ['cancelada', 'completada'])
            ->where('fecha', '>=', $today)
            ->with(['barber.user', 'service'])
            ->orderBy('fecha', 'asc')
            ->orderBy('hora_inicio', 'asc')
            ->first();

        $appointments = Appointment::query()
            ->where('client_id', $clientId)
            ->with(['barber.user', 'service'])
            ->latest('fecha')
            ->latest('hora_inicio')
            ->paginate(12);

        return view('client.appointments.index', compact('appointments', 'stats', 'nextAppointment'));
    }

    public function create(Request $request): View
    {
        $barbers  = Barber::query()->with('user:id,name')->where('activo', true)->get();
        $services = Service::query()->where('activo', true)->orderBy('nombre')->get();
        $products = Product::query()
            ->where('stock_actual', '>', 0)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria', 'precio_venta', 'imagen', 'descripcion']);

        $preselectedBarber = null;
        if ($request->filled('barber_id')) {
            $preselectedBarber = $barbers->first(fn($b) => (string) $b->id === (string) $request->barber_id);
        }

        $settings = BarbershopSetting::query()->first();

        return view('client.appointments.create', compact('barbers', 'services', 'products', 'preselectedBarber', 'settings'));
    }

    public function store(StoreClientAppointmentRequest $request): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client, 403);

        $data = $request->validated();
        $service = Service::findOrFail($data['service_id']);

        // Calculate end time
        $start = Carbon::parse($data['fecha'].' '.$data['hora_inicio']);
        $end = $start->copy()->addMinutes($service->duracion_min);

        $payload = array_merge($data, [
            'client_id' => $client->id,
            'hora_fin' => $end->format('H:i:s'),
            'estado' => 'pendiente',
        ]);

        try {
            $this->appointmentService->createAppointment($payload);
        } catch (AppointmentConflictException $exception) {
            return back()->withInput()->withErrors(['hora_inicio' => $exception->getMessage()]);
        }

        return redirect()->route('client.appointments.index')->with('status', 'Cita agendada correctamente.');
    }

    public function edit(Appointment $appointment): View
    {
        $client = auth()->user()->clientProfile;

        abort_if(! $client || $appointment->client_id !== $client->id, 403);

        $barbers = Barber::query()->with('user:id,name')->where('activo', true)->get(['id', 'user_id']);
        $services = Service::query()->where('activo', true)->orderBy('nombre')->get();

        return view('client.appointments.edit', compact('appointment', 'barbers', 'services'));
    }

    public function update(UpdateClientAppointmentRequest $request, Appointment $appointment): RedirectResponse
    {
        $client = $request->user()->clientProfile;
        abort_if(! $client || $appointment->client_id !== $client->id, 403);

        $data = $request->validated();
        $service = Service::findOrFail($data['service_id']);

        // Calculate end time
        $start = Carbon::parse($data['fecha'].' '.$data['hora_inicio']);
        $end = $start->copy()->addMinutes($service->duracion_min);

        $payload = array_merge($data, [
            'client_id' => $client->id,
            'hora_fin' => $end->format('H:i:s'),
            'estado' => in_array($appointment->estado, ['completada', 'cancelada', 'no_asistio'], true) ? $appointment->estado : 'pendiente',
        ]);

        try {
            $this->appointmentService->updateAppointment($appointment->id, $payload);
        } catch (AppointmentConflictException $exception) {
            return back()->withInput()->withErrors(['hora_inicio' => $exception->getMessage()]);
        }

        return redirect()->route('client.appointments.index')->with('status', 'Cita reprogramada correctamente.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $client = auth()->user()->clientProfile;

        abort_if(! $client || $appointment->client_id !== $client->id, 403);

        $policyHours = (int) (BarbershopSetting::query()->value('politica_cancelacion') ?? 24);

        $appointmentDateTime = Carbon::parse($appointment->fecha->format('Y-m-d').' '.$appointment->hora_inicio);
        $hoursDiff = now()->diffInHours($appointmentDateTime, false);

        if ($hoursDiff < $policyHours) {
            return back()->withErrors([
                'general' => "No se puede cancelar con menos de {$policyHours} horas de anticipación.",
            ]);
        }

        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => now(),
        ]);

        return redirect()->route('client.appointments.index')->with('status', 'Cita cancelada correctamente.');
    }
}
