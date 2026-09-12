<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Plan de membresía recurrente (roadmap P1): definido por administración,
 * cobrado mensualmente via Stripe Subscriptions. El beneficio es un
 * descuento % sobre cada servicio (ver MembershipService::activeDiscountFor()
 * y PaymentService), nunca mayor recompensa que el descuento por nivel de
 * lealtad -- se aplica el más alto de los dos, nunca ambos sumados.
 */
class MembershipPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio_mensual',
        'descuento_pct',
        // Product/Price de Stripe (ver StripePaymentService::createMonthlyPrice()).
        // Si se actualiza precio_mensual, se crea un Price nuevo y este campo
        // apunta al nuevo -- las suscripciones ya activas conservan el Price
        // con el que se contrataron hasta que el cliente renueve un plan.
        'stripe_product_id',
        'stripe_price_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio_mensual' => 'decimal:2',
            'descuento_pct' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function clientMemberships(): HasMany
    {
        return $this->hasMany(ClientMembership::class);
    }
}
