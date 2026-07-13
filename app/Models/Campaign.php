<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'titulo',
        'cuerpo',
        'cta_label',
        'cta_url',
        'segmento',
        'destinatarios',
        'enviado_por',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'integer',
        ];
    }
}
