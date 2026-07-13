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
        'opened_by',       // ids de usuarios que abrieron (unicos)
        'clicked_by',      // ids de usuarios que hicieron clic (unicos)
    ];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'integer',
            'programada_para' => 'datetime',
            'enviada_en' => 'datetime',
            'opened_by' => 'array',
            'clicked_by' => 'array',
        ];
    }

    public function opensCount(): int
    {
        return count($this->opened_by ?? []);
    }

    public function clicksCount(): int
    {
        return count($this->clicked_by ?? []);
    }

    /**
     * Tasa (0-100) de un contador sobre los destinatarios.
     */
    public function rate(int $count): int
    {
        return $this->destinatarios > 0 ? (int) round(($count / $this->destinatarios) * 100) : 0;
    }
}
