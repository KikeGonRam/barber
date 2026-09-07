<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma un usuario autenticado a la estructura consumida por la app móvil.
 * Reemplaza el antiguo método privado userPayload().
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completion = $this->profileCompletion();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'roles' => $this->roleNames()->values(),
            'profile_complete' => $completion['complete'],
            'profile_missing' => $completion['missing'],
            'client_id' => $this->clientProfile?->id,
            'barber_id' => $this->barberProfile?->id,
        ];
    }
}
