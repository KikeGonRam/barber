<?php

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Alta de servicio del catálogo (corte, barba, etc.).
 */
class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_min' => ['required', 'integer', 'min:5', 'max:600'],
            'imagen' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Un servicio nuevo se activa por defecto (true) si el formulario no
        // envía el checkbox 'activo' explícitamente.
        $this->merge([
            'activo' => $this->boolean('activo', true),
        ]);
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del servicio es obligatorio.',
            'categoria.required' => 'La categoría es obligatoria.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.min' => 'El precio no puede ser negativo.',
            'duracion_min.required' => 'La duración es obligatoria.',
            'duracion_min.min' => 'La duración mínima es de 5 minutos.',
        ];
    }
}
