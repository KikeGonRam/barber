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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('appointments')
            ->logFillable()
            ->logOnlyDirty();
    }
}
