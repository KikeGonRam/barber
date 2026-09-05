<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Resultado de la rifa/sorteo mensual de lealtad: qué cliente ganó qué
 * premio en un mes dado ('mes' se guarda como string 'YYYY-MM'). El premio
 * se reclama una sola vez como descuento del 100% en un cobro (ver
 * RaffleService/PaymentService::create()) y caduca si no se usa a tiempo.
 */
class RaffleResult extends Model
{
    use HasFactory;

    // Días desde que se gana el sorteo hasta que el premio caduca sin reclamarse.
    const VIGENCIA_DIAS = 60;

    protected $fillable = [
        'client_id',
        'mes',      // 'YYYY-MM'
        'premio',
        'nivel_ganador',
        'vence_en',
        'reclamado_en',
        'appointment_id', // cita en la que se reclamó el premio (null si no se ha usado)
    ];

    protected function casts(): array
    {
        return [
            'vence_en' => 'datetime',
            'reclamado_en' => 'datetime',
        ];
    }

    // Cliente ganador de este resultado de rifa.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isClaimed(): bool
    {
        return $this->reclamado_en !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isClaimed() && $this->vence_en !== null && $this->vence_en->isPast();
    }

    // Puede aplicarse a un cobro: ni ya reclamado, ni caducado.
    public function isRedeemable(): bool
    {
        return ! $this->isClaimed() && ! $this->isExpired();
    }
}
