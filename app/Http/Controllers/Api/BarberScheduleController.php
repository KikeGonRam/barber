<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BarberSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Horarios de Barbero
 *
 * Endpoints para gestionar los horarios del barbero.
 */
class BarberScheduleController extends Controller
{
    /**
     * Obtener Horarios
     *
     * Devuelve los horarios del barbero autenticado.
     *
     * @response {
     *  "schedules": [
     *    {
     *      "id": 1,
     *      "day_of_week": "monday",
     *      "start_time": "09:00",
     *      "end_time": "18:00",
     *      "is_active": true
     *    }
     *  ]
     * }
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (!$barber) {
            return response()->json([
                'message' => 'No tienes perfil de barbero.',
            ], 403);
        }

        $schedules = BarberSchedule::where('barber_id', $barber->id)
            ->orderBy('day_of_week')
            ->get();

        return response()->json([
            'schedules' => $schedules,
        ]);
    }

    /**
     * Actualizar Horarios
     *
     * Actualiza los horarios del barbero autenticado.
     *
     * @bodyParam schedules array required Array de horarios. Example: [{"day_of_week":"monday","start_time":"09:00","end_time":"18:00","is_active":true}]
     * @bodyParam schedules.*.day_of_week string required Día de la semana (monday, tuesday, etc.). Example: monday
     * @bodyParam schedules.*.start_time string required Hora de inicio (HH:MM). Example: 09:00
     * @bodyParam schedules.*.end_time string required Hora de fin (HH:MM). Example: 18:00
     * @bodyParam schedules.*.is_active boolean Si el horario está activo. Example: true
     *
     * @response {
     *  "message": "Horarios actualizados correctamente",
     *  "schedules": [...]
     * }
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $barber = $user->barberProfile;

        if (!$barber) {
            return response()->json([
                'message' => 'No tienes perfil de barbero.',
            ], 403);
        }

        $validated = $request->validate([
            'schedules' => ['required', 'array'],
            'schedules.*.day_of_week' => ['required', 'string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.is_active' => ['sometimes', 'boolean'],
        ]);

        // Eliminar horarios existentes
        BarberSchedule::where('barber_id', $barber->id)->delete();

        // Crear nuevos horarios
        $schedules = [];
        foreach ($validated['schedules'] as $scheduleData) {
            $schedule = BarberSchedule::create([
                'barber_id' => $barber->id,
                'day_of_week' => $scheduleData['day_of_week'],
                'start_time' => $scheduleData['start_time'],
                'end_time' => $scheduleData['end_time'],
                'is_active' => $scheduleData['is_active'] ?? true,
            ]);

            $schedules[] = $schedule;
        }

        return response()->json([
            'message' => 'Horarios actualizados correctamente',
            'schedules' => $schedules,
        ]);
    }
}
