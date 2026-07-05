<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class RaffleResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'mes',      // 'YYYY-MM'
        'premio',
        'nivel_ganador',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
