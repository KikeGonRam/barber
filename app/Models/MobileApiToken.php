<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Token de autenticación para la app móvil, análogo a un personal access
 * token. Solo se guarda el hash (token_hash); el token en texto plano se
 * entrega una única vez al emitirlo y no puede recuperarse después.
 */
class MobileApiToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'token_hash',
        'abilities',
        'last_used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // Usuario dueño del token.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
