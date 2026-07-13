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
        'estado',          // 'programada' | 'enviada'
        'programada_para', // fecha/hora de envio programado (null = inmediata)
        'enviada_en',
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'integer',
            'programada_para' => 'datetime',
            'enviada_en' => 'datetime',
        ];
    }
}
