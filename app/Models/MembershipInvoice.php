<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Registro de un cobro exitoso (alta o renovación) de una membresía
 * recurrente -- existe solo para que CashCloseService pueda sumar estos
 * ingresos reales al corte de caja del día, ya que Stripe cobra estas
 * renovaciones automáticamente sin que el staff capture nada localmente.
 */
class MembershipInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_membership_id',
        'monto',
        'stripe_invoice_id',
        'pagado_en',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'pagado_en' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }
}
