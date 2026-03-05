<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected function casts(): array
    {
        return [
            'redes_sociales' => 'array',
        ];
    }
}
