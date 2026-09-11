<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\ClientAlreadyBookedException;
use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\RaffleResult;
use App\Models\Service;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentService;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Payment\DepositService;
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
        private readonly DepositService $deposits,
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
        $isClient = $user?->hasRole('cliente') && $user->clientProfile;

        // withCount('payments') alimenta AppointmentResource::has_payment --
        // el frontend del cliente lo usa para saber si mostrar "Pagar con
        // tarjeta" (autopago, Fase B) en una cita cobrable que aún no tiene
        // pago registrado.
        $query = Appointment::query()->with(['client.user', 'barber.user', 'service'])->withCount('payments')->latest('fecha')->latest('hora_inicio');

        if ($isClient) {
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

        $resource = AppointmentResource::collection($appointments);

        // "Mis Citas" (cliente): mismas stats/próxima-cita destacada que ya
        // calculaba Client\ClientAppointmentController::index() (web), para
        // que el frontend Nuxt no tenga que volver a agregar esta lógica.
        // Puramente aditivo: admin/recepción/barbero no ven este bloque.
        if ($isClient) {
            $clientId = (string) $user->clientProfile->id;
            $today = now()->startOfDay();
            $todayStr = $today->toDateString();

            $allEstados = Appointment::where('client_id', $clientId)->get(['estado', 'fecha']);
            $stats = [
                'total' => $allEstados->count(),
                'proximas' => $allEstados->filter(fn ($a) => ! in_array($a->estado, ['cancelada', 'completada', 'no_asistio'], true)
                    && substr((string) $a->fecha, 0, 10) >= $todayStr)->count(),
                'completadas' => $allEstados->where('estado', 'completada')->count(),
                'canceladas' => $allEstados->where('estado', 'cancelada')->count(),
            ];

            $next = Appointment::where('client_id', $clientId)
                ->whereNotIn('estado', ['cancelada', 'completada', 'no_asistio'])
                ->where('fecha', '>=', $today)
                ->with(['barber.user', 'service'])
                ->orderBy('fecha')
                ->orderBy('hora_inicio')
                ->first();

            $resource = $resource->additional([
                'stats' => $stats,
                'next' => $next ? new AppointmentResource($next) : null,
                'cancellation_policy_hours' => (int) (BarbershopSetting::cached()?->politica_cancelacion ?? 24),
            ]);
        }

        return $resource->response();
    }

    /**
     * Agenda propia del barbero autenticado, con el mismo periodo, filtro y
     * estadisticas que la vista web de Mi Agenda.
     */
    public function barberAgenda(Request $request): JsonResponse
    {
        $barber = $request->user()?->barberProfile;
        abort_if(! $barber, 403, 'No tienes perfil de barbero.');
        assert($barber instanceof Barber);

        $validated = $request->validate([
            'period' => ['sometimes', 'string', 'in:day,week'],
            'estado' => ['sometimes', 'nullable', 'string', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'offset' => ['sometimes', 'integer', 'between:-365,365'],
        ]);

        $period = $validated['period'] ?? 'day';
        $estado = $validated['estado'] ?? '';
        $offset = (int) ($validated['offset'] ?? 0);
        $baseDate = now()->addDays($offset);
        $periodStart = $period === 'week' ? $baseDate->copy()->startOfWeek()->startOfDay() : $baseDate->copy()->startOfDay();
        $periodEnd = $period === 'week' ? $baseDate->copy()->endOfWeek()->endOfDay() : $baseDate->copy()->endOfDay();

        $allPeriod = Appointment::query()
            ->where('barber_id', (string) $barber->id)
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->get();
        $total = $allPeriod->count();

        $query = Appointment::query()
            ->where('barber_id', (string) $barber->id)
            ->with(['client.user', 'barber.user', 'service'])
            ->whereBetween('fecha', [$periodStart, $periodEnd])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        return response()->json([
            'data' => AppointmentResource::collection($query->get()),
            'period' => $period,
            'estado' => $estado,
            'offset' => $offset,
            'range' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
                'label' => $period === 'week'
                    ? $periodStart->translatedFormat('d M').' - '.$periodEnd->translatedFormat('d M Y')
                    : $baseDate->translatedFormat('l, d \d\e F \d\e Y'),
            ],
            'stats' => [
                'completed_count' => Appointment::query()->where('barber_id', (string) $barber->id)->where('estado', 'completada')->count(),
                'income_total' => (float) Appointment::query()->where('barber_id', (string) $barber->id)->where('estado', 'completada')->sum('precio_cobrado'),
                'productivity' => $total > 0 ? (int) round($allPeriod->where('estado', 'completada')->count() / $total * 100) : 0,
                'total_period' => $total,
                'pending_period' => $allPeriod->where('estado', 'pendiente')->count(),
                'confirmed_period' => $allPeriod->where('estado', 'confirmada')->count(),
                'in_process_period' => $allPeriod->where('estado', 'en_proceso')->count(),
                'completed_period' => $allPeriod->where('estado', 'completada')->count(),
                'cancelled_period' => $allPeriod->where('estado', 'cancelada')->count(),
                'no_show_period' => $allPeriod->where('estado', 'no_asistio')->count(),
            ],
        ]);
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
     * Citas cobrables (aprobadas por el barbero, sin pago aun) para el
     * selector de "Nuevo Cobro" — puerto directo de la consulta que usa
     * Payment\PaymentController::create() (web), mas los datos de
     * lealtad/premio de rifa del cliente que esa vista precalcula para el
     * preview de descuento. El monto real cobrado NUNCA se calcula aqui:
     * esto es solo para que el staff vea el precio base y el descuento
     * potencial antes de cobrar; PaymentService::create() vuelve a leer
     * todo del lado servidor al procesar el pago (ver guardrail #13).
     */
    public function chargeable(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');

        $appointments = Appointment::query()
            ->whereIn('estado', AppointmentStatusService::CHARGEABLE)
            ->whereDoesntHave('payments')
            ->with(['client.user', 'barber.user', 'service'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        $clientIds = $appointments->pluck('client.id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
        $activePrizes = empty($clientIds) ? collect() : RaffleResult::whereIn('client_id', $clientIds)
            ->whereNull('reclamado_en')
            ->where('vence_en', '>=', now())
            ->get()
            ->keyBy('client_id');

        return response()->json([
            'data' => $appointments->map(function (Appointment $appt) use ($activePrizes) {
                $nivel = $appt->client?->nivel ?? 'nuevo';
                $premio = $appt->client ? $activePrizes->get((string) $appt->client->id) : null;

                return [
                    'id' => (string) $appt->id,
                    'code' => $appt->code,
                    'fecha' => optional($appt->fecha)->toDateString(),
                    'hora_inicio' => $appt->hora_inicio,
                    'client_name' => $appt->client?->user?->name,
                    'barber_name' => $appt->barber?->user?->name,
                    'service_name' => $appt->service?->nombre,
                    'precio' => (float) ($appt->service?->precio ?? 0),
                    'nivel' => $nivel,
                    'nivel_label' => LoyaltyService::LEVEL_LABELS[$nivel] ?? $nivel,
                    'nivel_pct' => LoyaltyService::discountPct($nivel),
                    'puntos_disponibles' => (int) ($appt->client?->puntos ?? 0),
                    'premio_rifa' => $premio?->premio,
                ];
            })->values(),
        ]);
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

        // Política anti-no-show: se decide UNA VEZ aquí, con el historial del
        // cliente en este momento (ver DepositService::requirementFor()).
        // Aplica sin importar quién reserva -- si staff agenda a nombre de un
        // cliente con historial de inasistencias, también necesita saberlo.
        $deposito = $this->deposits->requirementFor($client, $service);

        $payload = [
            'client_id' => $client->id,
            'barber_id' => $validated['barber_id'],
            'service_id' => $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            'estado' => $validated['estado'] ?? 'pendiente',
            'notas' => $validated['notas'] ?? null,
            'deposito_requerido' => $deposito['requerido'],
            'deposito_monto' => $deposito['monto'],
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
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);
        $isOwner = $user?->hasRole('cliente') && $user->clientProfile && (string) $appointment->client_id === (string) $user->clientProfile->id;

        abort_if(! $isStaff && ! $isOwner, 403, 'No autorizado para editar esta cita.');

        if ($isOwner && ! $isStaff) {
            return $this->rescheduleAsClient($request, $appointment);
        }

        $validated = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'barber_id' => ['required', 'exists:barbers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'estado' => ['required', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        // El estado se valida contra la misma maquina de estados que
        // updateStatus() usa para el barbero -- este endpoint no debe poder
        // saltarse transiciones (p.ej. completada -> pendiente) solo porque
        // es un PUT de edicion completa en vez del PATCH de solo-estado.
        if ($validated['estado'] !== (string) $appointment->estado
            && ! $this->statusService->canTransition((string) $appointment->estado, $validated['estado'])) {
            return response()->json([
                'message' => "No se puede pasar la cita de '{$appointment->estado}' a '{$validated['estado']}'.",
            ], 422);
        }

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

        if ($validated['estado'] === 'cancelada' && (string) $appointment->estado !== 'cancelada') {
            $payload['cancelada_en'] = now();
        }

        if ($validated['estado'] === 'en_proceso' && (string) $appointment->estado !== 'en_proceso') {
            $payload['servicio_iniciado_en'] = now();
        }

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
     * Reagenda una cita propia (rol cliente): mismas reglas que
     * Client\ClientAppointmentController::update() (web) — solo puede tocar
     * barbero/servicio/fecha/hora/notas (nunca client_id/estado), y solo si
     * la cita sigue en un estado que el cliente puede gestionar (pendiente o
     * confirmada, y aún no ha iniciado).
     */
    private function rescheduleAsClient(Request $request, Appointment $appointment): JsonResponse
    {
        if (! $this->clientCanManage($appointment)) {
            return response()->json(['message' => 'Esta cita ya no se puede reagendar.'], 422);
        }

        $validated = $request->validate([
            'barber_id' => ['required', 'string', 'exists:barbers,id'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) $service->duracion_min);

        $payload = [
            'client_id' => (string) $appointment->client_id,
            'barber_id' => $validated['barber_id'],
            'service_id' => $validated['service_id'],
            'fecha' => $validated['fecha'],
            'hora_inicio' => $start->format('H:i:00'),
            'hora_fin' => $end->format('H:i:00'),
            // Un reagendamiento del cliente vuelve a "pendiente" para que el
            // barbero la confirme de nuevo, igual que la versión web.
            'estado' => 'pendiente',
            'notas' => $validated['notas'] ?? null,
        ];

        try {
            $this->appointmentService->updateAppointment((string) $appointment->id, $payload);
        } catch (ClientAlreadyBookedException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (AppointmentConflictException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Cita reprogramada correctamente.',
            'data' => new AppointmentResource($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }

    /**
     * El cliente solo puede editar/cancelar citas que no han terminado su
     * ciclo de vida (pendiente/confirmada) y cuyo horario de inicio sigue en
     * el futuro — igual que Client\ClientAppointmentController::canClientManage().
     */
    private function clientCanManage(Appointment $appointment): bool
    {
        if (! in_array($appointment->estado, ['pendiente', 'confirmada'], true)) {
            return false;
        }

        return $this->appointmentStartsAt($appointment)->isFuture();
    }

    private function appointmentStartsAt(Appointment $appointment): Carbon
    {
        return Carbon::parse($appointment->fecha->format('Y-m-d').' '.$appointment->hora_inicio);
    }

    /**
     * Cambia el estado de una cita respetando la máquina de estados de
     * AppointmentStatusService (transición válida + rol autorizado).
     *
     * Antes exigía hasRole('barbero') y contradecía a la propia máquina de
     * estados: ROLE_MAP ya declaraba a administrador y recepcionista
     * autorizados en TODAS las transiciones, pero este guard les devolvía 403,
     * y roleCanSet() quedaba sin usarse en todo el proyecto. Como PUT
     * /appointments/{cita} exige el payload completo con
     * 'fecha' => after_or_equal:today, el efecto neto era que recepción no
     * podía marcar "no asistió" ni "completada" en una cita del día anterior
     * por ningún camino — justo cuando un no-show se marca después del hecho.
     *
     * El barbero sigue acotado a SUS citas. El rol cliente no entra aquí
     * aunque ROLE_MAP lo liste para 'cancelada': cancela con DELETE
     * /appointments/{cita}, que además valida su propia ventana de tiempo.
     *
     * Actualizar Estado de Cita
     *
     * Cambia solo el estado (y opcionalmente las notas), sin exigir el payload
     * completo de la cita. Permitido a administrador, recepcionista y al
     * barbero asignado.
     *
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     *
     * @bodyParam estado string required Estado destino. Debe ser una transición válida desde el estado actual. Example: confirmada
     * @bodyParam notas string Notas opcionales de la cita. Example: Llegó 5 minutos tarde.
     *
     * @response 200 {
     *  "message": "Estado actualizado correctamente.",
     *  "data": { "code": "jfb7ffye", "estado": "confirmada" }
     * }
     * @response 403 {
     *  "message": "Solo puedes cambiar el estado de tus propias citas."
     * }
     * @response 422 {
     *  "message": "No se puede pasar la cita de 'completada' a 'pendiente'."
     * }
     */
    public function updateStatus(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 403, 'No autorizado para cambiar el estado de esta cita.');

        $role = match (true) {
            $user->hasRole('administrador') => 'administrador',
            $user->hasRole('recepcionista') => 'recepcionista',
            $user->hasRole('barbero') => 'barbero',
            default => null,
        };

        // IDs de MongoDB: comparar como string, nunca con === sobre objetos.
        abort_if(
            $role === 'barbero' && (string) $appointment->barber_id !== (string) ($user->barberProfile->id ?? ''),
            403,
            'Solo puedes cambiar el estado de tus propias citas.'
        );

        $validated = $request->validate([
            'estado' => ['required', 'in:pendiente,confirmada,en_proceso,completada,cancelada,no_asistio'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        // Legalidad de la transición ANTES que el rol: si la cita ya está en
        // un estado terminal, el motivo real es ese y no el rol de quien pide
        // (un admin que intenta completada -> pendiente debe leer "no se
        // puede", no "tu rol no puede").
        if (! $this->statusService->canTransition((string) $appointment->estado, $validated['estado'])) {
            return response()->json([
                'message' => "No se puede pasar la cita de '{$appointment->estado}' a '{$validated['estado']}'.",
            ], 422);
        }

        abort_if(
            ! $this->statusService->roleCanSet($validated['estado'], $role),
            403,
            'Tu rol no puede poner la cita en ese estado.'
        );

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

        if ($isOwner && ! $isAdmin) {
            if (! $this->clientCanManage($appointment)) {
                return response()->json(['message' => 'Esta cita ya no se puede cancelar.'], 422);
            }

            $startsAt = $this->appointmentStartsAt($appointment);
            $policyHours = (int) (BarbershopSetting::cached()?->politica_cancelacion ?? 24);

            if (now()->diffInHours($startsAt, false) < $policyHours) {
                return response()->json([
                    'message' => "Solo puedes cancelar con al menos {$policyHours} horas de anticipación.",
                ], 422);
            }
        }

        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => now(),
        ]);

        // Cancelación a tiempo (ya validada arriba), nunca no-show: si había
        // un depósito verificado, se devuelve (ver DepositService::refundIfAny()).
        $this->deposits->refundIfAny($appointment);

        $this->notifier->cancelled($appointment, 'app movil');

        return response()->json([
            'message' => 'Cita cancelada correctamente.',
        ]);
    }
}
