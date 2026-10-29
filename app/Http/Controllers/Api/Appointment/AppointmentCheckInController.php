<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Check-in de la cita («Llegó»): la recepción (o el barbero de la cita) marca que el cliente ya está en el local.
 *
 * No cambia el estado de la cita: la máquina de estados (AppointmentStatusService) sigue siendo la única autoridad
 * sobre pendiente → confirmada → en_proceso → completada. Solo registra cuándo llegó el cliente (`llegada_en`) para
 * que el barbero lo vea en su día y la recepción sepa a quién atender o cobrar.
 */
class AppointmentCheckInController extends Controller
{
    /**
     * Marcar que el cliente llegó
     *
     * Solo para citas de hoy en estado pendiente o confirmada. Es idempotente: si ya estaba marcada, devuelve la
     * misma hora de llegada sin cambiar nada.
     *
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     *
     * @response {"message": "Llegada registrada.", "llegada_en": "2026-10-29T10:02:11+00:00"}
     */
    public function store(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 403, 'No autorizado.');

        $isStaff = $user->hasAnyRole(['administrador', 'recepcionista']);
        $isOwnerBarber = $user->hasRole('barbero') && (string) $appointment->barber_id === (string) ($user->barberProfile->id ?? '');
        abort_if(! $isStaff && ! $isOwnerBarber, 403, 'No autorizado para registrar la llegada de esta cita.');

        if (! in_array($appointment->estado, ['pendiente', 'confirmada'], true)) {
            return response()->json([
                'message' => "No se puede registrar la llegada de una cita en estado '{$appointment->estado}'.",
            ], 422);
        }

        if (! Carbon::parse($appointment->fecha)->isToday()) {
            return response()->json([
                'message' => 'Solo se puede registrar la llegada de las citas de hoy.',
            ], 422);
        }

        if ($appointment->getAttribute('llegada_en') === null) {
            $appointment->setAttribute('llegada_en', now());
            $appointment->save();
        }

        return response()->json([
            'message' => 'Llegada registrada.',
            'llegada_en' => $appointment->getAttribute('llegada_en')?->toIso8601String(),
        ]);
    }
}
