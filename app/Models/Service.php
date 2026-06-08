<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'categoria',
        'precio',
        'duracion_min',
        'imagen',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
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
