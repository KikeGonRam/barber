<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class BarbershopSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'logo',
        'direccion',
        'telefono',
        'horario_apertura',
        'horario_cierre',
        'politica_cancelacion',
        'redes_sociales',
        'datos_bancarios',
        'maintenance_mode',
    ];

    protected function casts(): array
    {
        return [
            'redes_sociales' => 'array',
            'datos_bancarios' => 'array',
            'maintenance_mode' => 'boolean',
        ];
    }
}
