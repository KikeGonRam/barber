<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Resultado de la rifa/sorteo mensual de lealtad: qué cliente ganó qué
 * premio en un mes dado ('mes' se guarda como string 'YYYY-MM').
 */
class RaffleResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'mes',      // 'YYYY-MM'
        'premio',
        'nivel_ganador',
    ];

    // Cliente ganador de este resultado de rifa.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
