<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Autoedición del perfil público del barbero (no incluye name/email: esos
 * campos los gestiona el admin vía UpdateBarberRequest). Todos los campos
 * son opcionales porque el barbero puede actualizar solo la foto, o solo
 * la descripción, sin tener que reenviar el resto.
 */
class UpdateBarberProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('barbero') ?? false;
    }

    public function rules(): array
    {
        return [
            'especialidades' => ['nullable', 'string', 'max:1000'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
