<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Copia persistente (Mongo) de un intercambio pregunta/respuesta del
 * chatbot, solo para usuarios autenticados. Aditiva: el historial "real"
 * que usa el motor del chatbot (memoria de preguntas similares, detección
 * de follow-up, prompt aumentado) sigue viviendo en sesión sin cambios
 * (ChatbotContextService), porque el widget Blade depende de eso y sigue
 * funcionando igual. Esto existe solo porque un cliente Bearer-token sin
 * cookie de sesión (Nuxt) nunca conserva nada entre requests — confirmado
 * en vivo el 2026-09-06: dos llamadas seguidas a POST /api/v1/chatbot/query
 * con el mismo token, sin cookie, devolvían historial vacío en la segunda.
 */
class ChatMessage extends Model
{
    protected $fillable = [
        'user_id',
        'message',
        'response',
        'type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
