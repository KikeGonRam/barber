<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Cargo por inasistencia: se genera cuando el personal marca una cita como «no asistió». Se cobra solo a la tarjeta
 * guardada del cliente si es posible; si no, queda como adeudo (`pendiente`) que bloquea nuevas reservas hasta que
 * recepción lo cobre en sucursal o administración lo condone. Ver NoShowFeeService.
 *
 * @property string $appointment_id
 * @property string $client_id
 * @property numeric-string|float $monto
 * @property numeric-string|float $monto_base
 * @property numeric-string|float $credito_anticipo
 * @property int $porcentaje
 * @property string $estado
 * @property string|null $metodo_cobro
 * @property string|null $stripe_payment_id
 * @property string|null $motivo
 * @property string|null $cobrado_por
 * @property Carbon|null $cobrado_en
 */
class NoShowFee extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PAGADO = 'pagado';

    public const ESTADO_CONDONADO = 'condonado';

    protected $fillable = [
        'appointment_id',
        'client_id',
        // Lo que falta por cobrar. `monto_base` es el cargo completo (% del servicio) y `credito_anticipo` lo que ya
        // estaba pagado de forma anticipada y se descuenta (un depósito anti-no-show retenido no se cobra dos veces).
        'monto',
        'monto_base',
        'credito_anticipo',
        'porcentaje',
        'estado',
        // tarjeta (automático) | efectivo | transferencia (recepción) | anticipo (cubierto por un pago previo)
        'metodo_cobro',
        'stripe_payment_id',
        'motivo',
        'cobrado_por',
        'cobrado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'monto_base' => 'decimal:2',
            'credito_anticipo' => 'decimal:2',
            'porcentaje' => 'integer',
            'cobrado_en' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
