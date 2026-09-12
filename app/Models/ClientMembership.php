<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Suscripción de un cliente a un MembershipPlan, respaldada por una
 * Subscription real de Stripe. `bloquea_membresia` es un booleano espejo de
 * "estado != cancelada" (Mongo solo permite igualdad en
 * partialFilterExpression, no $in/$nin) -- mismo patrón que `activa` en
 * Waitlist, usado por el índice único que impide dos membresías activas al
 * mismo tiempo para un cliente.
 */
class ClientMembership extends Model
{
    use HasFactory;

    // Creada en Stripe con payment_behavior=default_incomplete: existe pero
    // aún no se confirmó el primer pago (ver MembershipService::subscribe()).
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ACTIVA = 'activa';

    // La renovación falló y Stripe está reintentando (Smart Retries) -- el
    // cliente deja de recibir el descuento mientras dure este estado, pero la
    // suscripción sigue viva hasta que Stripe la cancele definitivamente.
    public const ESTADO_PAGO_FALLIDO = 'pago_fallido';

    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'client_id',
        'membership_plan_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'estado',
        // true mientras el estado no sea 'cancelada' -- ver nota de clase.
        'bloquea_membresia',
        // El cliente pidió cancelar pero sigue con beneficio hasta que
        // termine el periodo ya pagado (ver MembershipService::cancel()).
        'cancelar_al_finalizar',
        'periodo_actual_fin',
    ];

    protected function casts(): array
    {
        return [
            'bloquea_membresia' => 'boolean',
            'cancelar_al_finalizar' => 'boolean',
            'periodo_actual_fin' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }
}
