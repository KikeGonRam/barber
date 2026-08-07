<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición administrativa del barbero (incluye datos de la cuenta de usuario
 * asociada: name/email). authorize() delega el control de acceso al
 * middleware de rol en la ruta, por eso siempre devuelve true aquí.
 */
class UpdateBarberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // El unique de email debe ignorar al propio usuario del barbero que
        // se está editando; si no, guardar sin cambiar el email fallaría
        // porque "ya existe" (es el mismo registro).
        $barber = $this->route('barber');
        $userId = $barber?->user_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'especialidades' => ['nullable', 'string', 'max:1000'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'activo' => ['nullable', 'boolean'],
        ];
    }
}
