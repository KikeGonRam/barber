<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Client extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'user_id',
        'telefono',
        'fecha_nacimiento',
        'preferencias_notificacion',
        'slug',
        'nivel',
        'puntos',
        'total_citas',
    ];

    protected function slugSource(): string
    {
        $user = $this->user ?? ($this->user_id ? User::find($this->user_id) : null);

        return $user?->name ?? 'cliente';
    }

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'preferencias_notificacion' => 'array',
            'puntos' => 'integer',
            'total_citas' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    public function getNivelAttribute($value): string
    {
        return $value ?? 'nuevo';
    }

    public function getPuntosAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    public function getTotalCitasAttribute($value): int
    {
        return (int) ($value ?? 0);
    }
}
