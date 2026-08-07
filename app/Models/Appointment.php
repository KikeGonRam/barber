<?php

namespace App\Models;

use App\Traits\HasPublicCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Una cita agendada por un cliente con un barbero para un servicio.
 *
 * Es el modelo central del negocio: controla la maquina de estados de
 * `estado` (pendiente/confirmada/en curso/completada/cancelada, etc.),
 * cobros (metodo_pago, precio_cobrado), recordatorios (reminder_24h/2h) y
 * bitacora via SoftDeletes + LogsActivity (Spatie). Usa HasPublicCode para
 * generar el 'code' publico mostrado al cliente.
 */
class Appointment extends Model
{
    use HasFactory, HasPublicCode, LogsActivity, SoftDeletes;

    protected $fillable = [
        'client_id',
        'barber_id',
        'service_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
        'notas',
        'metodo_pago',
        'precio_cobrado',
        'productos',
        'motivo_reagendamiento',
        'cancelada_en',
        'code',
        'confirmation_sent_at',
        'reminder_24h_sent_at',
        'reminder_2h_sent_at',
        'cancellation_notified_at',
        'servicio_iniciado_en',
        'ultimo_aviso_barbero_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'productos' => 'array',
            'precio_cobrado' => 'float',
            'cancelada_en' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'reminder_24h_sent_at' => 'datetime',
            'reminder_2h_sent_at' => 'datetime',
            'cancellation_notified_at' => 'datetime',
            'servicio_iniciado_en' => 'datetime',
            'ultimo_aviso_barbero_en' => 'datetime',
        ];
    }

    // Cliente que reservo la cita.
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    // Barbero asignado a la cita.
    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    // Servicio (corte, barba, etc.) que se presta en la cita.
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    // Pagos asociados a esta cita (puede haber mas de uno, ej. abono + saldo).
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // Movimientos de inventario (consumo de productos) generados por esta cita.
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // Configuracion de Spatie Activitylog: registra solo cambios (dirty) en campos fillable,
    // bajo el log_name 'appointments' para poder filtrarlos en la bitacora.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('appointments')
            ->logFillable()
            ->logOnlyDirty();
    }
}
