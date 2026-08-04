<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'propina' => 'decimal:2',
            'ocr_monto_detectado' => 'decimal:2',
            'revisado_en' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('payments')
            ->logFillable()
            ->logOnlyDirty();
    }
}
