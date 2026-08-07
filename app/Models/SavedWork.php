<?php

namespace App\Models;

use Database\Factories\SavedWorkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Marca un trabajo (Work) del portafolio como guardado/favorito por un
 * usuario.
 */
class SavedWork extends Model
{
    /** @use HasFactory<SavedWorkFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_id',
    ];

    // Usuario que guardó el trabajo.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Trabajo guardado.
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
