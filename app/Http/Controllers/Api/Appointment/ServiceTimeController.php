<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\Appointment\ServiceTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Agregar tiempo al servicio en curso (ver ServiceTimeService). «Terminar ya» no necesita endpoint propio: es pasar la
 * cita a `completada` por PATCH /appointments/{cita}/status.
 */
class ServiceTimeController extends Controller
{
    public function __construct(private readonly ServiceTimeService $serviceTime) {}

    /**
     * Agregar tiempo al servicio en curso
     *
     * El barbero de la cita (o recepción/administración) agrega minutos a un servicio en proceso. Si el nuevo fin
     * choca con la siguiente cita responde 422 con `choca_con`; repetir con `forzar=true` extiende de todos modos y
     * avisa al siguiente cliente.
     *
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     *
     * @bodyParam minutos integer required Minutos a agregar (10, 15, 20 o 30). Example: 10
     * @bodyParam forzar boolean Extender aunque choque con la siguiente cita. Example: false
     */
    public function extend(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 403, 'No autorizado.');

        $isStaff = $user->hasAnyRole(['administrador', 'recepcionista']);
        $isOwnerBarber = $user->hasRole('barbero') && (string) $appointment->barber_id === (string) ($user->barberProfile->id ?? '');
        abort_if(! $isStaff && ! $isOwnerBarber, 403, 'Solo el barbero de la cita o el personal pueden agregar tiempo.');

        $validated = $request->validate([
            'minutos' => ['required', 'integer'],
            'forzar' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $this->serviceTime->extend($appointment, (int) $validated['minutos'], (bool) ($validated['forzar'] ?? false));
        } catch (InvalidAppointmentTransitionException|\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (! $result['extended']) {
            $next = $result['conflict'];
            $nextStart = substr((string) $next?->getAttribute('hora_inicio'), 0, 5);

            return response()->json([
                'message' => "Agregar ese tiempo choca con la siguiente cita ({$nextStart}). Confirma para extender de todos modos y avisar a ese cliente.",
                'puede_forzar' => true,
                'choca_con' => [
                    'code' => $next?->getAttribute('code'),
                    'hora_inicio' => $nextStart,
                ],
            ], 422);
        }

        return response()->json([
            'message' => 'Tiempo agregado.',
            'data' => new AppointmentResource($appointment->fresh(['client.user', 'barber.user', 'service'])),
        ]);
    }
}
