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
            'barber_id' => ['required', 'string', 'exists:barbers,id'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'metodo_pago' => ['nullable', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'productos' => ['nullable', 'array'],
            'productos.*.product_id' => ['required', 'string'],
            'productos.*.nombre' => ['required', 'string'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('productos') && is_string($this->productos)) {
            $decoded = json_decode($this->productos, true);
            $this->merge(['productos' => is_array($decoded) ? $decoded : []]);
        }
    }
}
