<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Plantilla de paquete prepagado (roadmap P1): "N usos de este servicio por
 * $precio", definida por administración. No es lo que compra el cliente --
 * eso es ClientPackage, una instancia con su propio contador de usos
 * restantes. Mismo espíritu que ServiceCombo (varios servicios a un precio
 * conjunto) pero para MISMO servicio repetido varias veces.
 */
class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'service_id',
        'cantidad_usos',
        'precio',
        // Días de vigencia desde la compra; null = sin vencimiento.
        'vigencia_dias',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_usos' => 'integer',
            'precio' => 'decimal:2',
            'vigencia_dias' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function clientPackages(): HasMany
    {
        return $this->hasMany(ClientPackage::class);
    }
}
