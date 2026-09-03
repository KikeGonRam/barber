<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de pago de una cita. El gate real de "solo se puede cobrar una
 * cita confirmada" vive en la máquina de estados de la cita (ver
 * project_appointment_flow), no aquí: este request solo valida forma de los
 * datos del pago.
 */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['required', 'string', 'exists:appointments,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', Rule::in(['efectivo', 'tarjeta', 'transferencia'])],
            'propina' => ['nullable', 'numeric', 'min:0'],
            'puntos_canjeados' => ['nullable', 'integer', 'min:0'],
            // 'tarjeta' ya no es un registro manual: siempre es un cobro real via
            // Stripe (beta), asi que exige el id del PaymentIntent ya confirmado.
            'stripe_payment_id' => ['required_if:metodo_pago,tarjeta', 'nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliza 'propina'/'puntos_canjeados' a 0 si no se envían, para
        // que el resto del flujo no tenga que lidiar con null.
        $this->merge([
            'propina' => $this->input('propina', 0),
            'puntos_canjeados' => $this->input('puntos_canjeados', 0),
        ]);
    }

    public function messages(): array
    {
        return [
            'appointment_id.required' => 'La cita es obligatoria.',
            'appointment_id.exists' => 'La cita seleccionada no existe.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.min' => 'El monto debe ser mayor a 0.',
            'metodo_pago.required' => 'El método de pago es obligatorio.',
            'metodo_pago.in' => 'El método de pago no es válido.',
        ];
    }
}
