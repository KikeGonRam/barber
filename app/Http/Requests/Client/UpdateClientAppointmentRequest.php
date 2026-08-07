<?php

namespace App\Http\Requests\Client;

/**
 * Reagendamiento de cita por el cliente: hereda las reglas de creación y
 * solo agrega el motivo del reagendamiento (opcional, para trazabilidad).
 */
class UpdateClientAppointmentRequest extends StoreClientAppointmentRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo_reagendamiento' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
