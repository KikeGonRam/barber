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

    /**
     * @return array<string, array<string, mixed>>
     */
    public function bodyParameters(): array
    {
        return [
            'especialidades' => [
                'description' => 'Especialidades del barbero (ej. Degradados, Barba tradicional).',
                'example' => 'Degradados, Diseños freestyle, Barba',
            ],
            'descripcion' => [
                'description' => 'Biografía o presentación del barbero.',
                'example' => 'Más de 5 años de experiencia en barbería urbana y tradicional.',
            ],
            'foto' => [
                'description' => 'Imagen de perfil o foto profesional del barbero.',
            ],
        ];
    }
}
