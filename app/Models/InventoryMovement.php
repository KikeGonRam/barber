<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Movimiento de inventario de un producto (entrada/salida de stock), con
 * bitacora via Spatie LogsActivity. Puede estar ligado a una Appointment
 * (consumo de producto durante una cita) o ser un ajuste manual de un User.
 */
class InventoryMovement extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'product_id',
        'tipo',
        'cantidad',
        'motivo',
        'appointment_id',
        'user_id',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    // Producto afectado por el movimiento.
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Cita que origino el consumo del producto (null si es ajuste manual).
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // Usuario que registro el movimiento.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Configuracion de Spatie Activitylog: registra solo cambios (dirty) en campos fillable,
    // bajo el log_name 'inventory'.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('inventory')
            ->logFillable()
            ->logOnlyDirty();
    }
}
