<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('cliente') ?? false;
    }

    public function rules(): array
    {
        return [
            'barber_id' => ['required', 'integer', 'exists:barbers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
