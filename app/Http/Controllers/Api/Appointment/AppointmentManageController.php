<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\ClientAlreadyBookedException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Services\Appointment\AppointmentManageLinkService;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentService;
use App\Services\Payment\DepositService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Gestión de cita por enlace
 *
 * Deja que un cliente vea, reagende o cancele SU cita desde el enlace del
 * recordatorio, sin iniciar sesión. Antes el recordatorio mandaba a
 * /my/appointments, detrás de middleware ['auth','client']: quien no
 * recordaba su contraseña simplemente no iba, y eso se traduce en no-shows.
 *
 * Autoriza el token opaco de la cita, no una sesión
 * (AppointmentManageLinkService). Aplica exactamente las mismas reglas que
 * el cliente autenticado: solo citas pendientes/confirmadas y futuras, y la
 * ventana de cancelación configurada por la barbería. El enlace no puede ser
 * una puerta más permisiva que la app.
 */
class AppointmentManageController extends Controller
{
    public function __construct(
        private readonly AppointmentManageLinkService $links,
        private readonly AppointmentService $appointments,
        private readonly AppointmentNotifier $notifier,
        private readonly DepositService $deposits,
    ) {}

    /**
     * Rechaza con 404, no 403: para quien prueba tokens al azar, una cita
     * inexistente y una con token equivocado deben verse igual.
     */
    private function authorizeToken(Appointment $appointment, Request $request): void
    {
        abort_if(! $this->links->isValid($appointment, $request->query('t', $request->input('t'))), 404);
    }

    private function payload(Appointment $appointment): array
    {
        $appointment->load(['barber.user', 'service']);

        return [
            'code' => $appointment->code,
            'fecha' => optional($appointment->fecha)->toDateString(),
            'hora_inicio' => $appointment->hora_inicio,
            'estado' => $appointment->estado,
            'servicio' => $appointment->service?->nombre,
            'duracion_min' => $appointment->service?->duracion_min,
            'precio' => $appointment->service?->precio,
            'barbero' => $appointment->barber?->user?->name,
            'barber_id' => (string) $appointment->barber_id,
            'service_id' => (string) $appointment->service_id,
            'politica_horas' => $this->links->policyHours(),
            // Lo que el enlace permite AHORA, para que la pantalla no ofrezca
            // un botón que el servidor va a rechazar.
            'puede_gestionar' => $this->links->isManageable($appointment),
            'dentro_de_politica' => $this->links->withinPolicy($appointment),
        ];
    }

    /**
     * Ver cita
     *
     * @unauthenticated
     *
     * @queryParam t string required Token del enlace del recordatorio. Example: 9f2c...
     */
    public function show(Appointment $appointment, Request $request): JsonResponse
    {
        $this->authorizeToken($appointment, $request);

        return response()->json(['data' => $this->payload($appointment)]);
    }

    /**
     * Cancelar cita desde el enlace
     *
     * @unauthenticated
     */
    public function cancel(Appointment $appointment, Request $request): JsonResponse
    {
        $this->authorizeToken($appointment, $request);

        if (! $this->links->isManageable($appointment)) {
            return response()->json(['message' => 'Esta cita ya no se puede cancelar.'], 422);
        }

        if (! $this->links->withinPolicy($appointment)) {
            return response()->json([
                'message' => 'Las cancelaciones deben hacerse con al menos '.$this->links->policyHours().' horas de anticipación. Comunícate con la barbería.',
            ], 422);
        }

        $appointment->update([
            'estado' => 'cancelada',
            'cancelada_en' => Carbon::now(),
            // Libera el hueco: el índice único parcial de citas activas se
            // apoya en este campo (ver Fase 3 del roadmap).
            'bloquea_horario' => false,
        ]);

        // withinPolicy() ya garantizó arriba que esto es una cancelación a
        // tiempo, no un no-show -- devuelve el depósito verificado si había.
        $this->deposits->refundIfAny($appointment);

        $this->notifier->statusChanged($appointment, 'cancelada');

        return response()->json([
            'message' => 'Tu cita fue cancelada.',
            'data' => $this->payload($appointment->fresh()),
        ]);
    }

    /**
     * Reagendar cita desde el enlace
     *
     * Mantiene barbero y servicio: mover el horario es lo que evita el
     * no-show. Cambiar de servicio o de barbero sigue siendo un flujo de la
     * app con sesión.
     *
     * @unauthenticated
     *
     * @bodyParam fecha string required Nueva fecha (Y-m-d). Example: 2026-09-20
     * @bodyParam hora_inicio string required Nueva hora (HH:mm). Example: 16:30
     */
    public function reschedule(Appointment $appointment, Request $request): JsonResponse
    {
        $this->authorizeToken($appointment, $request);

        if (! $this->links->isManageable($appointment)) {
            return response()->json(['message' => 'Esta cita ya no se puede reagendar.'], 422);
        }

        if (! $this->links->withinPolicy($appointment)) {
            return response()->json([
                'message' => 'Los cambios deben hacerse con al menos '.$this->links->policyHours().' horas de anticipación. Comunícate con la barbería.',
            ], 422);
        }

        $validated = $request->validate([
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ]);

        $service = Service::find($appointment->service_id);
        $start = Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $end = $start->copy()->addMinutes((int) ($service->duracion_min ?? 30));

        try {
            $this->appointments->updateAppointment((string) $appointment->id, [
                'client_id' => (string) $appointment->client_id,
                'barber_id' => (string) $appointment->barber_id,
                'service_id' => (string) $appointment->service_id,
                'fecha' => $validated['fecha'],
                'hora_inicio' => $start->format('H:i:00'),
                'hora_fin' => $end->format('H:i:00'),
                // Vuelve a "pendiente" para que la barbería la confirme de
                // nuevo, igual que el reagendamiento con sesión. Los flags de
                // recordatorio ya enviados los resetea updateAppointment()
                // cuando cambia fecha/hora, así que no se duplican aquí.
                'estado' => 'pendiente',
            ]);
        } catch (ClientAlreadyBookedException|AppointmentConflictException $exception) {
            // El backend sigue siendo la autoridad sobre choques de horario.
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $fresh = $appointment->fresh();
        $this->notifier->statusChanged($fresh, 'pendiente');

        return response()->json([
            'message' => 'Tu cita fue reagendada.',
            'data' => $this->payload($fresh),
        ]);
    }
}
