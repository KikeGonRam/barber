<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Pago asociado a una cita (Appointment). Incluye comprobante, revisión
 * manual/OCR del monto y bitácora de cambios vía Spatie Activitylog.
 */
class Payment extends Model
{
    use HasFactory, LogsActivity;

    public const ESTADO_VERIFICADO = 'verificado';

    public const ESTADO_PENDIENTE_VERIFICACION = 'pendiente_verificacion';

    public const ESTADO_RECHAZADO = 'rechazado';

    protected $fillable = [
        'appointment_id',
        'monto',
        'metodo_pago',
        'propina',
        'comprobante_pdf',
        'created_by',
        'estado',
        'comprobante_cliente',
        'ocr_texto',
        'ocr_monto_detectado',
        'revisado_por',
        'revisado_en',
        'motivo_rechazo',
        'monto_total',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'propina' => 'decimal:2',
            'ocr_monto_detectado' => 'decimal:2',
            'revisado_en' => 'datetime',
            'monto_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // monto_total se mantiene en el propio documento (en vez de calcularse
        // solo en la vista) para poder ordenar el listado de pagos por el total
        // real cobrado (monto + propina) — MongoDB no puede ordenar por un
        // campo derivado que no existe en el documento.
        static::saving(function (self $payment): void {
            $payment->monto_total = (float) $payment->monto + (float) $payment->propina;
        });
    }

    // Cita a la que corresponde este pago.
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    // Usuario que registró el pago (recepción/barbero).
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Usuario que revisó/verificó el comprobante.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    // Configura Activitylog: solo registra cambios en campos fillable y solo cuando hay diferencias reales.
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logFillable()
            ->logOnlyDirty();
    }
}
