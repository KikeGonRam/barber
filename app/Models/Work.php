<?php

namespace App\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Trabajo del portafolio publicado por un barbero (corte, diseño, etc.),
 * con imágenes/videos, comentarios y reacciones de otros usuarios.
 */
class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    protected $fillable = [
        'barbero_id',
        'title',
        'description',
        'work_date',
    ];

    // Usuario (barbero) autor del trabajo.
    public function barberUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'barbero_id');
    }

    // Imágenes/videos que componen este trabajo.
    public function images(): HasMany
    {
        return $this->hasMany(WorkImage::class);
    }

    // Comentarios recibidos en este trabajo.
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Reacciones recibidas en este trabajo.
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    // Usuarios que guardaron este trabajo como favorito.
    public function saves(): HasMany
    {
        return $this->hasMany(SavedWork::class);
    }

    // Si el usuario dado ya reaccionó a este trabajo.
    public function isReactedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->reactions()->where('user_id', $user->id)->exists();
    }

    // Si el usuario dado ya guardó este trabajo.
    public function isSavedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $this->saves()->where('user_id', $user->id)->exists();
    }
}
