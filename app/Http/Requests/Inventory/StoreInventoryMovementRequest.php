<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro manual de movimiento de inventario (entrada/salida de stock).
 */
class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasAnyRole(['administrador', 'recepcionista']) ?? false;
    }

    public function rules(): array
    {
        // Recepción solo puede registrar salidas (venta/consumo de producto);
        // solo el admin puede registrar entradas (reposición/compra de stock),
        // para evitar que recepción infle el inventario sin control.
        $typeRules = ['required', Rule::in(['entrada', 'salida'])];

        if ($this->user()?->hasRole('recepcionista')) {
            $typeRules = ['required', Rule::in(['salida'])];
        }

        return [
            'product_id' => ['required', 'string', 'exists:products,id'],
            'tipo' => $typeRules,
            'cantidad' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'appointment_id' => ['nullable', 'string', 'exists:appointments,id'],
            'fecha' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.in' => 'No tienes permisos para registrar ese tipo de movimiento.',
        ];
    }
}
