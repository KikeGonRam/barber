<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Solo un barbero puede cambiar el estado de sus propias citas desde su
 * agenda. No incluye "cancelada" ni "no_asistio" en la lista permitida:
 * esos estados los maneja el flujo de cancelación/recepción, no el barbero.
 */
class UpdateBarberAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('barbero') ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['pendiente', 'confirmada', 'en_proceso', 'completada'])],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
