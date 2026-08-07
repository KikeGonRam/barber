<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición del perfil de un cliente ya existente. A diferencia de
 * StoreClientProfileRequest, no valida password aquí (el cambio de
 * contraseña tiene su propio flujo).
 */
class UpdateClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // El unique de email ignora al propio usuario del cliente para no
        // rechazar el guardado cuando el email no cambió.
        $client = $this->route('client');
        $userId = $client?->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'pref_in_app' => ['nullable', 'boolean'],
            'pref_email' => ['nullable', 'boolean'],
            'pref_sms' => ['nullable', 'boolean'],
            'pref_whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
