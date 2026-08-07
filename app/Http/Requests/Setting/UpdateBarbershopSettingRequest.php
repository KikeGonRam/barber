<?php

namespace App\Http\Requests\Setting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Configuración global de la barbería (datos de contacto, horario, redes,
 * datos bancarios para pagos por transferencia). Acceso restringido por el
 * middleware de rol en la ruta, no aquí (authorize() siempre true).
 */
class UpdateBarbershopSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'horario_apertura' => ['nullable', 'date_format:H:i'],
            'horario_cierre' => ['nullable', 'date_format:H:i', 'after:horario_apertura'],
            // politica_cancelacion está en horas (1 a 168 = una semana):
            // cuánto tiempo antes de la cita el cliente puede cancelar sin penalización.
            'politica_cancelacion' => ['required', 'integer', 'min:1', 'max:168'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'tiktok' => ['nullable', 'string', 'max:255'],
            'clabe' => ['nullable', 'string', 'max:18'],
            'banco' => ['nullable', 'string', 'max:100'],
            'beneficiario' => ['nullable', 'string', 'max:150'],
            'concepto' => ['nullable', 'string', 'max:100'],
        ];
    }
}
