<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Registro/alta de un cliente nuevo (crea también el usuario asociado). El
 * unique de email no ignora ningún id porque aquí siempre es un registro
 * nuevo (a diferencia de UpdateClientProfileRequest).
 */
class StoreClientProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // 'password' es nullable porque este request también se reutiliza
            // cuando un admin/recepción crea el perfil de cliente sin definir
            // contraseña (el cliente la establece después).
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'telefono' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'pref_in_app' => ['nullable', 'boolean'],
            'pref_email' => ['nullable', 'boolean'],
            'pref_sms' => ['nullable', 'boolean'],
            'pref_whatsapp' => ['nullable', 'boolean'],
        ];
    }
}
