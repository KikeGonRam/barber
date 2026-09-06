<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Suscripción Web Push de un dispositivo/navegador concreto de un usuario
 * (un usuario puede tener varias: distintos navegadores/dispositivos). Se
 * identifica de forma única por 'endpoint' (lo asigna el navegador, no
 * nosotros) porque el cliente nunca conoce el _id de Mongo del registro.
 */
class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
    ];

    // Dueño de la suscripción.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
