<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulario genérico "Editar perfil" (Breeze/Jetstream), para cualquier
 * rol de usuario autenticado editando su propia cuenta.
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                // Ignora el propio id: si el usuario no cambia su email, no
                // debe fallar el unique contra su propio registro.
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
