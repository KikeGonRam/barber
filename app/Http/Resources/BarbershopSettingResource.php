<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforma la configuración de la barbería a la estructura consumida por la app móvil.
 * Reemplaza el antiguo método privado payload() de SettingController.
 */
class BarbershopSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'telefono' => $this->telefono,
            'horario_apertura' => $this->horario_apertura,
            'horario_cierre' => $this->horario_cierre,
            'politica_cancelacion' => $this->politica_cancelacion,
            'deposito_no_show_umbral' => $this->deposito_no_show_umbral ?? 2,
            'deposito_no_show_porcentaje' => $this->deposito_no_show_porcentaje ?? 50,
            'maintenance_mode' => (bool) $this->maintenance_mode,
            'redes_sociales' => $this->redes_sociales ?? [],
            'datos_bancarios' => $this->datos_bancarios ?? [],
        ];
    }
}
