<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotificationCollection;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Reemplazo Mongo del modelo de notificaciones base de Laravel (canal
 * 'database'), en la coleccion `notifications`. Schemaless ($guarded = [])
 * porque 'data' varia segun la clase de Notification que la genero.
 * `notifiable` es polimorfico: normalmente apunta a un User.
 */
class DatabaseNotification extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'notifications';

    protected $guarded = [];

    // Mongo usa ObjectId (string), no autoincrement numerico.
    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    // A quien pertenece la notificacion (tipicamente un User).
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // Marca como leida solo si aun no lo estaba (evita pisar el timestamp original).
    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

    // Revierte a no leida.
    public function markAsUnread(): void
    {
        if (! is_null($this->read_at)) {
            $this->forceFill(['read_at' => null])->save();
        }
    }

    public function read(): bool
    {
        return ! is_null($this->read_at);
    }

    public function unread(): bool
    {
        return is_null($this->read_at);
    }

    // Scope: solo notificaciones ya leidas.
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    // Scope: solo notificaciones pendientes de leer.
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // Usa la Collection especializada de Laravel (con helpers read()/unread()) en vez de la generica.
    public function newCollection(array $models = []): DatabaseNotificationCollection
    {
        return new DatabaseNotificationCollection($models);
    }
}
