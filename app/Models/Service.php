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
