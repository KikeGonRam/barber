<?php

namespace App\Http\Requests\Barber;

use Illuminate\Foundation\Http\FormRequest;

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
