<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Comentario (con calificacion opcional) dejado por un usuario en una
 * publicacion del muro social (Work). No confundir con BarberReview, que es
 * la reseña directa al barbero.
 */
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected $fillable = [
        'work_id',
        'user_id',
        'comment',
        'rating',
    ];

    // Autor del comentario.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Publicacion del muro a la que pertenece el comentario.
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
