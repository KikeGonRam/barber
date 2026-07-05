<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

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

    public function combos(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCombo::class, 'combo_service', 'service_id', 'combo_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
