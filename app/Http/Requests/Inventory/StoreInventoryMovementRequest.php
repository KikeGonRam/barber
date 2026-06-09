<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->hasAnyRole(['administrador', 'recepcionista']) ?? false;
    }

    public function rules(): array
    {
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
