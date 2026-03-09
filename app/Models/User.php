<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function barberProfile(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function notificationPreferences(): array
    {
        $defaults = [
            'in_app' => true,
            'email' => true,
            'sms' => false,
            'whatsapp' => false,
        ];

        $clientPreferences = $this->clientProfile?->preferencias_notificacion;

        if (! is_array($clientPreferences)) {
            return $defaults;
        }

        return array_merge($defaults, $clientPreferences);
    }

    public function wantsNotificationChannel(string $channel): bool
    {
        return (bool) ($this->notificationPreferences()[$channel] ?? false);
    }

    public function clientPhone(): ?string
    {
        return $this->clientProfile?->telefono;
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    public function savedWorks(): HasMany
    {
        return $this->hasMany(SavedWork::class);
    }
}
