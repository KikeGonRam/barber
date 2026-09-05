<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\ClientAlreadyBookedException;
use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\AppointmentStatusService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Citas
 *
 * Gestión de citas para clientes, barberos y administración.
 */
class AppointmentController extends Controller
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentNotifier $notifier,
        private readonly AppointmentStatusService $statusService,
    ) {}

    /**
     * Listar Citas
     *
     * Obtiene el historial de citas filtrado por el rol del usuario autenticado.
     * Los clientes solo ven sus citas, los barberos las suyas, y la administración todas.
     *
     * @authenticated
     *
     * @response {
     *  "data": [
     *    {
     *      "id": 1,
     *      "client_id": 10,
     *      "barber_id": 2,
     *      "service_id": 5,
     *      "fecha": "2026-04-15",
     *      "hora_inicio": "10:00:00",
     *      "hora_fin": "11:00:00",
     *      "estado": "confirmada",
     *      "notas": "Corte de cabello degradado.",
     *      "client_name": "Juan Pérez",
     *      "barber_name": "Carlos Barbero",
     *      "service_name": "Corte Clásico",
     *      "service_duration": 60,
     *      "created_at": "2026-04-10T15:00:00Z"
     *    }
     *  ]
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Appointment::query()->with(['client.user', 'barber.user', 'service'])->latest('fecha')->latest('hora_inicio');

        if ($user?->hasRole('cliente') && $user->clientProfile) {
            $query->where('client_id', (string) $user->clientProfile->id);
        } elseif ($user?->hasRole('barbero') && $user->barberProfile) {
            $query->where('barber_id', (string) $user->barberProfile->id);
        } elseif (! $user?->hasAnyRole(['administrador', 'recepcionista'])) {
            abort(403, 'No autorizado para consultar citas.');
        }

        // Filtros opcionales (admin/recepción — para clientes/barberos no
        // tiene sentido filtrar más allá de "las suyas", ya acotado arriba).
        // Aditivo: sin estos query params el comportamiento es idéntico al
        // de antes (últimas 50), no rompe consumidores existentes — ver
        // guardrail #11 de este repo (routes/api.php es un contrato externo).
        if ($user->hasAnyRole(['administrador', 'recepcionista'])) {
            $query->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->query('estado')))
                ->when($request->filled('barber_id'), fn ($q) => $q->where('barber_id', $request->query('barber_id')))
                ->when($request->filled('fecha'), fn ($q) => $q->whereDate('fecha', $request->query('fecha')));
        }

        $appointments = $query->limit(50)->get();

        return AppointmentResource::collection($appointments)->response();
    }

    /**
     * Citas del rango de fechas (y barbero opcional) como eventos de
     * FullCalendar — puerto directo de
     * Appointment\AppointmentController::calendarData() (web), para el
     * calendario del frontend Nuxt (ver
     * frontend-urban/.claude/skills/nuxt-migration-plan/SKILL.md, Fase 7).
     * Esa versión web solo es alcanzable con sesión + permiso
     * 'citas.gestionar'; aquí se restringe por rol vía token Bearer,
     * mismo criterio de admin/recepcionista.
     */
    public function calendarData(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado para consultar el calendario.');

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
                'id' => (string) $appt->id,
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
                ],
            ];
        });

        return response()->json($events);
    }

    /**
     * Crear Cita
     *
     * Registra una nueva cita en el sistema validando la disponibilidad del barbero.
     *
     * @authenticated
     *
     * @bodyParam barber_id int required El ID del barbero. Example: 2
     * @bodyParam service_id int required El ID del servicio. Example: 5
     * @bodyParam fecha date required La fecha de la cita (YYYY-MM-DD). Example: 2026-04-15
     * @bodyParam hora_inicio string required La hora de inicio (HH:mm). Example: 10:00
     * @bodyParam client_id int Requerido solo para Admin/Recepcionista. ID del cliente. Example: 10
     * @bodyParam estado string Requerido solo para Admin/Recepcionista. Uno de: pendiente, confirmada, en_proceso, completada, cancelada, no_asistio. Example: confirmada
     * @bodyParam notas string Opcional. Notas adicionales. Example: Traer foto de referencia.
     *
     * @response 201 {
     *  "message": "Cita creada correctamente.",
     *  "data": { "id": 1, "estado": "confirmada", ... }
     * }
     * @response 422 {
     *  "message": "El barbero ya tiene una cita en ese horario."
     * }
     * @response 403 {
     *  "message": "No autorizado para crear citas."
     * }
     */
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

        // El cliente reserva para sí mismo (creando su perfil Client si aún no existe);
        // admin/recepción reservan a nombre de un cliente existente indicado en el request
        $client = $user->hasRole('cliente')
            ? ($user->clientProfile ?? $user->clientProfile()->create())
            : Client::findOrFail($validated['client_id']);

        $service = Service::findOrFail($validated['service_id']);

        // hora_fin se calcula a partir de la duración del servicio, nunca la envía el cliente
        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) $service->duracion_min);

        $payload = [
            'client_id' => $client->id,
            'barber_id' => $validated['barber_id'],
            'service_id' => $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            'estado' => $validated['estado'] ?? 'pendiente',
            'notas' => $validated['notas'] ?? null,
        ];

        try {
            $appointment = $this->appointmentService->createAppointment($payload);
        } catch (ClientAlreadyBookedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (AppointmentConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cita creada correctamente.',
            'data' => new AppointmentResource($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ], 201);
    }

    /**
     * Edita una cita completa (todos los campos). Reservado a administración/recepción;
     * el barbero solo puede cambiar el estado vía updateStatus().
     */
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

        $service = Service::findOrFail($validated['service_id']);
        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) $service->duracion_min);

        $payload = [
            'client_id' => $validated['client_id'],
            'barber_id' => $validated['barber_id'],
            'service_id' => $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            'estado' => $validated['estado'],
            'notas' => $validated['notas'] ?? null,
        ];

        try {
            $this->appointmentService->updateAppointment((string) $appointment->id, $payload);
        } catch (ClientAlreadyBookedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (AppointmentConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cita actualizada correctamente.',
            'data' => new AppointmentResource($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }

    /**
     * Permite al barbero dueño de la cita cambiar su estado (respetando la máquina de estados
     * validada en AppointmentStatusService).
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        // Solo el barbero asignado a esta cita puede cambiar su estado — se compara como string por los IDs de MongoDB
        abort_if(! $user || ! $user->hasRole('barbero') || ! $user->barberProfile || (string) $appointment->barber_id !== (string) $user->barberProfile->id, 403);

        $validated = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->statusService->transition($appointment, $validated['estado']);
        } catch (InvalidAppointmentTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (array_key_exists('notas', $validated)) {
            $appointment->update(['notas' => $validated['notas']]);
        }

        $this->notifier->statusChanged($appointment, $validated['estado']);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
            'data' => new AppointmentResource($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }

    /**
     * Cancela una cita (soft-cancel: cambia estado a "cancelada", no elimina el registro).
     * Permitido al cliente dueño de la cita o a un administrador.
     */
    public function destroy(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user?->hasRole('cliente') && $user->clientProfile && (string) $appointment->client_id === (string) $user->clientProfile->id;
        $isAdmin = $user?->hasRole('administrador');

        abort_if(! $isOwner && ! $isAdmin, 403);

        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => now(),
        ]);

        $this->notifier->cancelled($appointment, 'app movil');

        return response()->json([
            'message' => 'Cita cancelada correctamente.',
        ]);
    }
}
