<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Reseña que un cliente deja sobre un barbero (calificacion + comentario),
 * distinta de los comentarios del muro social (ver Comment/Work).
 */
class BarberReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'barber_id',
        'client_id',
        'rating',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    // Barbero calificado.
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    // Cliente que dejo la reseña.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
