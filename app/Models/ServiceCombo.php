<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model;

class ServiceCombo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'precio_combo',
        'descuento',
    ];

    protected function casts(): array
    {
        return [
            'precio_combo' => 'decimal:2',
            'descuento' => 'decimal:2',
        ];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'combo_service', 'combo_id', 'service_id');
    }
}
