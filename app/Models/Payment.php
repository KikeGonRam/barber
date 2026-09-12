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

    // Depósito devuelto al cliente (cancelación a tiempo). No confundir con
    // ESTADO_RECHAZADO: rechazado es "staff nunca lo validó", reembolsado es
    // "sí era dinero real, y se le regresó". Ver DepositService::refundIfAny().
    public const ESTADO_REEMBOLSADO = 'reembolsado';

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
        'puntos_canjeados',
        'stripe_payment_id',
        'raffle_result_id',
        // Paquete prepagado usado para cubrir este cobro (null si se pagó
        // normal). Ver PackageService::redeem().
        'client_package_id',
        'bloquea_cita',
        'loyalty_refund_reconciled_at',
        // true = depósito anti-no-show cobrado al reservar (ver DepositService);
        // false/ausente = cobro normal del servicio (PaymentService). Ambos
        // viven en la misma colección porque comparten revisión de
        // transferencia, corte de caja y CashCloseService, pero nunca se
        // confunden entre sí (ver el índice único compuesto de la migración).
        'es_deposito',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'propina' => 'decimal:2',
            'ocr_monto_detectado' => 'decimal:2',
            'revisado_en' => 'datetime',
            'monto_total' => 'decimal:2',
            'puntos_canjeados' => 'integer',
            'bloquea_cita' => 'boolean',
            'loyalty_refund_reconciled_at' => 'datetime',
            'es_deposito' => 'boolean',
        ];
    }

    // Pagos existentes antes de esta feature no tienen el campo -> nunca son depósito.
    public function getEsDepositoAttribute($value): bool
    {
        return (bool) $value;
    }

    // Pagos existentes antes de esta feature no tienen el campo -> 0 puntos canjeados.
    public function getPuntosCanjeadosAttribute($value): int
    {
        return (int) ($value ?? 0);
    }

    protected static function booted(): void
    {
        // monto_total se mantiene en el propio documento (en vez de calcularse
        // solo en la vista) para poder ordenar el listado de pagos por el total
        // real cobrado (monto + propina) — MongoDB no puede ordenar por un
        // campo derivado que no existe en el documento.
        static::saving(function (self $payment): void {
            $payment->monto_total = (float) $payment->monto + (float) $payment->propina;

            // bloquea_cita replica exactamente el criterio de
            // PaymentRepository::existsForAppointment() (todo menos
            // 'rechazado' cuenta como "esta cita ya tiene un pago"). Existe
            // para que el indice unico parcial de la migracion
            // add_payment_appointment_unique_index pueda expresar "pago
            // activo" como una igualdad simple: MongoDB no permite $ne/$nin
            // dentro de un partialFilterExpression, mismo motivo que
            // Appointment::bloquea_horario (ver Fase 3, auditoria de Fase 4).
            $payment->bloquea_cita = $payment->estado !== self::ESTADO_RECHAZADO;
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
