<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotificationCollection;
use MongoDB\Laravel\Eloquent\Model;

class DatabaseNotification extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'notifications';
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function markAsRead(): void
    {
        if (is_null($this->read_at)) {
            $this->forceFill(['read_at' => $this->freshTimestamp()])->save();
        }
    }

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

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function newCollection(array $models = []): DatabaseNotificationCollection
    {
        return new DatabaseNotificationCollection($models);
    }
}
