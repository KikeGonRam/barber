<?php

namespace App\Services\Membership;

use App\Exceptions\Domain\MembershipException;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\MembershipInvoice;
use App\Models\MembershipPlan;
use App\Services\Payment\StripePaymentService;
use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Membresía recurrente (roadmap P1, la última pieza del roadmap de mercado):
 * suscripción mensual real via Stripe Subscriptions que otorga un descuento %
 * en cada servicio, mientras esté activa -- nunca sumado al descuento por
 * nivel de lealtad, siempre el mayor de los dos (ver PaymentService).
 */
class MembershipService
{
    public function __construct(
        private readonly StripePaymentService $stripe,
    ) {}

    /**
     * Planes disponibles para contratar (catálogo público del cliente).
     */
    public function activePlans()
    {
        return MembershipPlan::where('activo', true)->orderBy('precio_mensual')->get();
    }

    /**
     * Membresía vigente (o más reciente) del cliente, si tiene alguna.
     */
    public function currentFor(Client $client): ?ClientMembership
    {
        return ClientMembership::where('client_id', (string) $client->id)
            ->latest()
            ->first();
    }

    /**
     * Descuento % activo del cliente por su membresía, o 0 si no tiene una
     * vigente. Solo 'activa' otorga el beneficio -- si el pago de renovación
     * está fallando (pago_fallido) el cliente deja de recibirlo hasta que
     * Stripe logre cobrar de nuevo, mismo criterio que cualquier suscripción
     * con pago atrasado.
     */
    public function activeDiscountFor(Client $client): int
    {
        $membership = ClientMembership::where('client_id', (string) $client->id)
            ->where('estado', ClientMembership::ESTADO_ACTIVA)
            ->first();

        if (! $membership) {
            return 0;
        }

        $plan = MembershipPlan::find($membership->membership_plan_id);

        return $plan ? (int) $plan->descuento_pct : 0;
    }

    /**
     * Contrata un plan: crea (o reutiliza) el Customer de Stripe y una
     * Subscription en modo default_incomplete. El registro local queda en
     * 'pendiente' hasta que el webhook de Stripe confirme el primer cobro
     * (ver StripeWebhookController::onSubscriptionUpdated()). Devuelve el
     * client_secret que el frontend necesita para confirmar el pago.
     */
    public function subscribe(Client $client, MembershipPlan $plan): array
    {
        if (! $plan->activo) {
            throw new MembershipException('Este plan de membresía ya no está disponible.');
        }

        // Check de aplicación para un mensaje de error limpio en el caso
        // normal (no atómico); el índice único parcial sobre
        // (client_id, bloquea_membresia) de la migración es la garantía real
        // ante dos solicitudes casi simultáneas, mismo patrón que
        // WaitlistService::join() / ReferralService::link().
        if (ClientMembership::where('client_id', (string) $client->id)->where('bloquea_membresia', true)->exists()) {
            throw new MembershipException('Ya tienes una membresía activa o pendiente. Cancélala antes de contratar otra.');
        }

        $client->loadMissing('user');
        $customerId = $client->stripe_customer_id;

        if (! $customerId) {
            $customerId = $this->stripe->createCustomer(
                $client->user?->email ?? 'sin-correo@urbanblade.mx',
                $client->user?->name ?? 'Cliente UrbanBlade',
                ['client_id' => (string) $client->id],
            );
            $client->update(['stripe_customer_id' => $customerId]);
        }

        $result = $this->stripe->createSubscription($customerId, $plan->stripe_price_id, [
            'client_id' => (string) $client->id,
            'membership_plan_id' => (string) $plan->id,
        ]);

        try {
            $membership = ClientMembership::create([
                'client_id' => (string) $client->id,
                'membership_plan_id' => (string) $plan->id,
                'stripe_customer_id' => $customerId,
                'stripe_subscription_id' => $result['subscription_id'],
                'estado' => ClientMembership::ESTADO_PENDIENTE,
                'bloquea_membresia' => true,
                'cancelar_al_finalizar' => false,
            ]);
        } catch (BulkWriteException $e) {
            throw new MembershipException('Ya tienes una membresía activa o pendiente. Cancélala antes de contratar otra.');
        }

        return [
            'membership' => $membership,
            'client_secret' => $result['client_secret'],
        ];
    }

    /**
     * Cancela al final del periodo ya pagado (decisión de negocio, ver
     * StripePaymentService::cancelSubscriptionAtPeriodEnd()): el estado local
     * no cambia todavía -- sigue 'activa'/'pago_fallido' hasta que Stripe
     * confirme el cierre real via customer.subscription.deleted.
     */
    public function cancel(ClientMembership $membership): ClientMembership
    {
        if ($membership->estado === ClientMembership::ESTADO_CANCELADA) {
            throw new MembershipException('Esta membresía ya está cancelada.');
        }

        if ($membership->cancelar_al_finalizar) {
            throw new MembershipException('Ya solicitaste cancelar esta membresía; seguirá activa hasta el final del periodo ya pagado.');
        }

        $this->stripe->cancelSubscriptionAtPeriodEnd($membership->stripe_subscription_id);

        $membership->update(['cancelar_al_finalizar' => true]);

        return $membership->fresh();
    }

    /**
     * Sincroniza el estado local a partir de un evento
     * customer.subscription.updated de Stripe -- fuente de verdad tanto para
     * activaciones (primer pago confirmado) como para renovaciones y pagos
     * fallidos (Stripe transiciona el status de la Subscription a
     * 'past_due'/'unpaid' solo, sin un evento aparte).
     */
    public function syncFromStripeStatus(string $stripeSubscriptionId, string $stripeStatus, ?\DateTimeInterface $periodoActualFin, bool $cancelAtPeriodEnd): void
    {
        $membership = ClientMembership::where('stripe_subscription_id', $stripeSubscriptionId)->first();

        if (! $membership) {
            Log::warning('Stripe webhook: suscripción sin ClientMembership local', ['stripe_subscription_id' => $stripeSubscriptionId]);

            return;
        }

        $estado = match ($stripeStatus) {
            'active', 'trialing' => ClientMembership::ESTADO_ACTIVA,
            'canceled' => ClientMembership::ESTADO_CANCELADA,
            default => ClientMembership::ESTADO_PAGO_FALLIDO,
        };

        $membership->update([
            'estado' => $estado,
            'bloquea_membresia' => $estado !== ClientMembership::ESTADO_CANCELADA,
            'cancelar_al_finalizar' => $cancelAtPeriodEnd,
            'periodo_actual_fin' => $periodoActualFin,
        ]);
    }

    /**
     * Registra un cobro exitoso (alta o renovación) para que
     * CashCloseService lo sume al corte del día. Idempotente por
     * stripe_invoice_id -- Stripe puede entregar el mismo webhook
     * invoice.payment_succeeded dos veces casi al mismo tiempo (confirmado
     * en vivo). El check de aplicación de abajo da un log limpio en el caso
     * normal (no atómico); el índice único de la migración es la garantía
     * real ante dos entregas casi simultáneas, mismo patrón que
     * WaitlistService::join() / ReferralService::link().
     */
    public function recordSuccessfulInvoice(string $stripeSubscriptionId, string $stripeInvoiceId, float $monto, ?\DateTimeInterface $pagadoEn): void
    {
        if (MembershipInvoice::where('stripe_invoice_id', $stripeInvoiceId)->exists()) {
            Log::info('Stripe webhook: cobro de membresía ya registrado, se omite', ['stripe_invoice_id' => $stripeInvoiceId]);

            return;
        }

        $membership = ClientMembership::where('stripe_subscription_id', $stripeSubscriptionId)->first();

        if (! $membership) {
            Log::warning('Stripe webhook: cobro de membresía sin ClientMembership local', ['stripe_subscription_id' => $stripeSubscriptionId]);

            return;
        }

        try {
            MembershipInvoice::create([
                'client_membership_id' => (string) $membership->id,
                'monto' => $monto,
                'stripe_invoice_id' => $stripeInvoiceId,
                'pagado_en' => $pagadoEn ?? now(),
            ]);
        } catch (BulkWriteException $e) {
            Log::info('Stripe webhook: cobro de membresía ya registrado (carrera con otra entrega del webhook), se omite', ['stripe_invoice_id' => $stripeInvoiceId]);
        }
    }

    /**
     * La suscripción ya no existe en Stripe (cancelación efectiva al cierre
     * del periodo, o Stripe canceló una 'incomplete' que nunca se confirmó).
     */
    public function markCancelledByStripe(string $stripeSubscriptionId): void
    {
        $membership = ClientMembership::where('stripe_subscription_id', $stripeSubscriptionId)->first();

        if (! $membership) {
            Log::warning('Stripe webhook: suscripción cancelada sin ClientMembership local', ['stripe_subscription_id' => $stripeSubscriptionId]);

            return;
        }

        $membership->update([
            'estado' => ClientMembership::ESTADO_CANCELADA,
            'bloquea_membresia' => false,
        ]);
    }
}
