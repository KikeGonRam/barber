<?php

namespace App\Http\Requests\Client;

class UpdateClientAppointmentRequest extends StoreClientAppointmentRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'motivo_reagendamiento' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
