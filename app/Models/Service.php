<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Servicio del catálogo de la barbería (corte, barba, etc.), con precio y
 * duración. Puede pertenecer a uno o más combos y tiene un slug generado
 * automáticamente a partir del nombre (trait HasSlug).
 */
class Service extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'nombre',
        'categoria',
        'precio',
        'duracion_min',
        'imagen',
        'descripcion',
        'activo',
        'slug',
    ];

    // Campo base usado por HasSlug para generar el slug.
    protected function slugSource(): string
    {
        return $this->nombre ?? 'servicio';
    }

    protected function casts(): array
    {
        return [
            'precio' => 'float',
            'activo' => 'boolean',
        ];
    }

    // Valores de `activo` que cuentan como encendido o apagado, incluidos los que quedaron como número o
    // texto antes de normalizarlo al guardar.
    private const ACTIVE_VALUES = [true, 1, '1'];

    private const INACTIVE_VALUES = [false, 0, '0'];

    public function scopeWhereActive($query, bool $activo = true)
    {
        return $query->whereIn('activo', $activo ? self::ACTIVE_VALUES : self::INACTIVE_VALUES);
    }

    // El formulario web (multipart) manda "1"/"0" y el cast boolean solo convierte al leer. MongoDB
    // compara por tipo: un "1" guardado como texto no sale en el catálogo (where('activo', true)).
    public function setActivoAttribute(mixed $value): void
    {
        $this->attributes['activo'] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    // Combos que incluyen este servicio (pivote combo_service).
    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCombo::class, 'combo_service', 'service_id', 'combo_id');
    }

    // Citas agendadas para este servicio.
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
