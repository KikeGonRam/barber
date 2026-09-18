<?php

namespace App\Http\Resources;

use App\Services\Loyalty\LoyaltyService;
use App\Services\Membership\MembershipService;
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
            'client' => $this->clientProfile ? [
                'telefono' => $this->clientProfile->telefono,
                'fecha_nacimiento' => $this->clientProfile->fecha_nacimiento?->format('Y-m-d'),
                'sexo' => $this->clientProfile->sexo,
                // Solo para el propio usuario autenticado (login/me): el
                // wizard de reserva lo muestra para que el precio en pantalla
                // coincida con lo que se cobra. No se calcula en listados de
                // otros usuarios para no disparar una consulta por fila.
                'descuento_activo_pct' => $this->when(
                    $request->user()?->is($this->resource),
                    fn () => LoyaltyService::bestDiscountPct(
                        $this->clientProfile->nivel ?? 'nuevo',
                        app(MembershipService::class)->activeDiscountFor($this->clientProfile),
                    ),
                ),
            ] : null,
        ];
    }
}
