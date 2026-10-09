<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Payment\ServiceTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ticket del servicio terminado (ver ServiceTicketService): lo que las apps muestran en pantalla al terminar y lo que
 * el cliente vuelve a abrir desde su historial.
 */
class AppointmentTicketController extends Controller
{
    public function __construct(private readonly ServiceTicketService $tickets) {}

    /**
     * Ticket de una cita completada
     *
     * Resumen del servicio y del pago (monto, propina, depósito aplicado, método) con el enlace temporal al
     * comprobante en PDF. Lo ve el cliente dueño de la cita, su barbero y el personal.
     *
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     */
    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user, 403, 'No autorizado.');

        $isStaff = $user->hasAnyRole(['administrador', 'recepcionista']);
        $isOwnerClient = $user->hasRole('cliente') && $user->clientProfile && (string) $appointment->client_id === (string) $user->clientProfile->id;
        $isOwnerBarber = $user->hasRole('barbero') && (string) $appointment->barber_id === (string) ($user->barberProfile->id ?? '');
        abort_if(! $isStaff && ! $isOwnerClient && ! $isOwnerBarber, 403, 'No autorizado para ver este ticket.');

        $ticket = $this->tickets->ticketFor($appointment);
        if ($ticket === null) {
            return response()->json(['message' => 'Esta cita todavía no tiene ticket: el servicio debe estar terminado y cobrado.'], 404);
        }

        return response()->json(['data' => $ticket]);
    }
}
