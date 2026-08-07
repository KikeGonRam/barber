<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Perfil de cliente vinculado 1-a-1 a un User (con rol cliente). Acumula
 * puntos de lealtad, nivel y contador de citas totales; el slug (HasSlug) se
 * usa en URLs publicas.
 */
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

    // Fuente del slug: nombre del User vinculado, o 'cliente' si no hay match.
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

    // Cuenta de usuario (login) asociada a este perfil de cliente.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Citas reservadas por este cliente.
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    // Historial de movimientos de puntos de lealtad (ganados/canjeados).
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }

    // Accessor: documentos viejos/incompletos en Mongo pueden no tener 'nivel' -> default 'nuevo'.
    public function getNivelAttribute($value): string
    {
        return $value ?? 'nuevo';
    }

    // Accessor: castea a entero y evita null cuando el documento no trae 'puntos'.
    public function getPuntosAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    // Accessor: castea a entero y evita null cuando el documento no trae 'total_citas'.
    public function getTotalCitasAttribute($value): int
    {
        return (int) ($value ?? 0);
    }
}
