<?php

namespace App\Http\Requests\User;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Edición de usuario existente desde el panel de administración.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ignora el propio id en el unique de email (editar sin cambiar el
        // email no debe fallar validación).
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            // password es nullable: dejar el campo vacío significa "no
            // cambiar la contraseña actual" (a diferencia del alta, donde es obligatorio).
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role' => [
                'required',
                'string',
                Rule::exists(Role::class, 'name')->where('guard_name', 'web'),
            ],
        ];
    }
}
