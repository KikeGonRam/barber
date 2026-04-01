<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Service;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AppointmentService $appointmentService) {}

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
