<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Referido (roadmap P1): un cliente (referrer) invita a otro (referee) con
 * su codigo_referido. La recompensa (puntos de lealtad para el referrer,
 * ver ReferralService/LoyaltyService::awardReferralPoints()) solo se
 * otorga cuando el referido completa su PRIMERA cita -- nunca por solo
 * registrarse, para no premiar cuentas falsas sin un cliente real detrás.
 */
class Referral extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_COMPLETADO = 'completado';

    protected $fillable = [
        'referrer_client_id',
        'referee_client_id',
        'estado',
        'recompensa_otorgada_en',
    ];

    protected function casts(): array
    {
        return [
            'recompensa_otorgada_en' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referrer_client_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'referee_client_id');
    }
}
