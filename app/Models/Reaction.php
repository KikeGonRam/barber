<?php

namespace App\Models;

use Database\Factories\ReactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Reacción (like/tipo) de un usuario a un trabajo (Work) del portafolio.
 */
class Reaction extends Model
{
    /** @use HasFactory<ReactionFactory> */
    use HasFactory;

    protected $fillable = [
        'work_id',
        'user_id',
        'type',
    ];

    // Usuario que reaccionó.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Trabajo al que pertenece la reacción.
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
