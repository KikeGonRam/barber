<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
