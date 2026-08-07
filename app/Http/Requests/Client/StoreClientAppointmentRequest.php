<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reserva de cita hecha por el propio cliente (self-service, no incluye
 * client_id: se infiere del usuario autenticado, a diferencia de
 * StoreAppointmentRequest usado por admin/recepción).
 */
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
            // Add-on de productos opcional: el cliente puede agregar productos
            // de la tienda a la reserva (ver project_shop_orders).
            'productos' => ['nullable', 'array'],
            'productos.*.product_id' => ['required', 'string'],
            'productos.*.nombre' => ['required', 'string'],
            'productos.*.precio' => ['required', 'numeric', 'min:0'],
            'productos.*.cantidad' => ['required', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // El formulario envía "productos" como JSON string (campo oculto de
        // un formulario normal, no un array anidado real); se decodifica
        // antes de validar para que las reglas "productos.*.x" apliquen.
        if ($this->has('productos') && is_string($this->productos)) {
            $decoded = json_decode($this->productos, true);
            $this->merge(['productos' => is_array($decoded) ? $decoded : []]);
        }
    }
}
