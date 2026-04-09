<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Notifications\AppointmentNotification;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Appointment::query()->with(['client.user', 'barber.user', 'service'])->latest('fecha')->latest('hora_inicio');

        if ($user?->hasRole('cliente') && $user->clientProfile) {
            $query->where('client_id', $user->clientProfile->id);
        } elseif ($user?->hasRole('barbero') && $user->barberProfile) {
            $query->where('barber_id', $user->barberProfile->id);
        }

        $appointments = $query->limit(50)->get()->map(fn (Appointment $appointment) => $this->appointmentPayload($appointment))->values();

        return response()->json(['data' => $appointments]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasAnyRole(['cliente', 'administrador', 'recepcionista']), 403, 'No autorizado para crear citas.');

        $rules = [
            'barber_id' => ['required', 'exists:barbers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];

        if ($user->hasAnyRole(['administrador', 'recepcionista'])) {
            $rules['client_id'] = ['required', 'exists:clients,id'];
            $rules['estado'] = ['nullable', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'];
        }

        $validated = $request->validate($rules);

        $client = $user->hasRole('cliente')
            ? ($user->clientProfile ?? $user->clientProfile()->create())
            : Client::findOrFail((int) $validated['client_id']);

        $service = Service::findOrFail($validated['service_id']);

        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) $service->duracion_min);

        $payload = [
            'client_id' => $client->id,
            'barber_id' => (int) $validated['barber_id'],
            'service_id' => (int) $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            'estado' => $validated['estado'] ?? 'pendiente',
            'notas' => $validated['notas'] ?? null,
        ];

        try {
            $appointment = $this->appointmentService->createAppointment($payload);
        } catch (AppointmentConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cita creada correctamente.',
            'data' => $this->appointmentPayload($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ], 201);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'Solo administración/recepción puede editar citas completas.');

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'barber_id' => ['required', 'exists:barbers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'estado' => ['required', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = Service::findOrFail((int) $validated['service_id']);
        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) $service->duracion_min);

        $payload = [
            'client_id' => (int) $validated['client_id'],
            'barber_id' => (int) $validated['barber_id'],
            'service_id' => (int) $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            'estado' => $validated['estado'],
            'notas' => $validated['notas'] ?? null,
        ];

        try {
            $this->appointmentService->updateAppointment((int) $appointment->id, $payload);
        } catch (AppointmentConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cita actualizada correctamente.',
            'data' => $this->appointmentPayload($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }

    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user || ! $user->hasRole('barbero') || ! $user->barberProfile || $appointment->barber_id !== $user->barberProfile->id, 403);

        $validated = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $appointment->update($validated);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => $this->appointmentPayload($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }

    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user?->hasRole('cliente') && $user->clientProfile && $appointment->client_id === $user->clientProfile->id;
        $isAdmin = $user?->hasRole('administrador');

        abort_if(! $isOwner && ! $isAdmin, 403);

        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => now(),
        ]);

        $appointment->load(['client.user', 'service']);

        if ($appointment->client?->user) {
            $appointment->client->user->notify(new AppointmentNotification(
                appointment: $appointment,
                subject: 'Cita cancelada',
                title: 'Tu cita fue cancelada',
                message: 'Tu cita fue cancelada desde la app móvil.',
            ));

            $appointment->update(['cancellation_notified_at' => now()]);
        }

        return response()->json([
            'message' => 'Cita cancelada correctamente.',
        ]);
    }

    private function appointmentPayload(Appointment $appointment): array
    {
        return [
            'id' => $appointment->id,
            'fecha' => optional($appointment->fecha)->toDateString(),
            'hora_inicio' => $appointment->hora_inicio,
            'hora_fin' => $appointment->hora_fin,
            'estado' => $appointment->estado,
            'notas' => $appointment->notas,
            'precio_cobrado' => $appointment->precio_cobrado,
            'client' => [
                'id' => $appointment->client?->id,
                'name' => $appointment->client?->user?->name,
            ],
            'barber' => [
                'id' => $appointment->barber?->id,
                'name' => $appointment->barber?->user?->name,
            ],
            'service' => [
                'id' => $appointment->service?->id,
                'nombre' => $appointment->service?->nombre,
                'precio' => $appointment->service?->precio,
                'duracion_min' => $appointment->service?->duracion_min,
            ],
        ];
    }
}