<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Alta de usuario desde el panel de administración (gestión de cuentas,
 * no el registro público). password es obligatorio porque es un usuario
 * nuevo (a diferencia de UpdateUserRequest).
 */
class StoreUserRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)],
            // El rol debe existir como Role de Spatie con guard "web"; evita
            // asignar nombres de rol inválidos o de otro guard (p.ej. api).
            'role' => [
                'required',
                'string',
                Rule::exists(Role::class, 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
