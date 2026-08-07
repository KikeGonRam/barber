<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Movimiento del programa de lealtad de un cliente (puntos ganados o
 * canjeados). Cada registro es un evento inmutable, no un saldo acumulado.
 */
class LoyaltyTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'tipo',         // 'ganado' | 'canjeado'
        'puntos',
        'descripcion',
        'referencia_id', // id de la entidad que originó el movimiento (cita, canje, etc.)
    ];

    protected function casts(): array
    {
        return ['puntos' => 'integer'];
    }

    // Cliente dueño de este movimiento de puntos.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
