<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Bloqueo entre usuarios del muro: quien bloquea deja de ver los comentarios del bloqueado
 * (política de contenido de usuarios de Google Play: poder bloquear contenido y usuarios).
 *
 * @property string $id
 * @property string $blocker_id
 * @property string $blocked_id
 */
class UserBlock extends Model
{
    protected $fillable = [
        'blocker_id',
        'blocked_id',
    ];

    // Usuario bloqueado.
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }
}
