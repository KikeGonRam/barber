<?php

namespace App\Http\Resources;

use App\Services\Appointment\AppointmentStatusService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma una cita a la estructura consumida por la app móvil y la web.
 * Reemplaza el antiguo método privado appointmentPayload().
 */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'fecha' => optional($this->fecha)->toDateString(),
            'hora_inicio' => $this->hora_inicio,
            'hora_fin' => $this->hora_fin,
            'estado' => $this->estado,
            'notas' => $this->notas,
            'precio_cobrado' => $this->precio_cobrado,
            // Solo presentes cuando el query de origen usó withCount('payments')
            // (AppointmentController::index()) -- el resto de usos de este
            // Resource simplemente no incluyen estos dos campos.
            'has_payment' => $this->when(isset($this->payments_count), fn () => $this->payments_count > 0),
            'is_chargeable' => $this->when(isset($this->payments_count), fn () => in_array($this->estado, AppointmentStatusService::CHARGEABLE, true)),
            'client' => [
                'id' => $this->client?->id,
                'user' => ['name' => $this->client?->user?->name],
            ],
            'barber' => [
                'id' => $this->barber?->id,
                'slug' => $this->barber?->slug,
                'user' => ['name' => $this->barber?->user?->name],
            ],
            'service' => [
                'id' => $this->service?->id,
                'nombre' => $this->service?->nombre,
                'precio' => $this->service?->precio,
                'duracion_min' => $this->service?->duracion_min,
            ],
        ];
    }
}
