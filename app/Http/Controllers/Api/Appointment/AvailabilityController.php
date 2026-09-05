<?php

namespace App\Http\Controllers\Api\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Services\Appointment\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expone la disponibilidad horaria de un barbero para agendar citas desde la app móvil/dashboard.
 */
class AvailabilityController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

    /**
     * Calcula los horarios libres de un barbero en una fecha dada, según la duración del servicio elegido.
     */
    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'barber_id' => 'required|exists:barbers,id',
            'service_id' => 'required|exists:services,id',
            'date' => 'required|date|after_or_equal:today',
        ]);

        $barber = Barber::findOrFail($request->barber_id);
        $service = Service::findOrFail($request->service_id);
        $date = $request->date;

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        return response()->json([
            'slots' => $slots,
        ]);
    }
}
