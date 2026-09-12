<?php

namespace App\Services\Payment;

use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

/**
 * Envoltorio delgado sobre el SDK de Stripe para pagos con tarjeta:
 * crea/consulta PaymentIntents y verifica si un pago se completo.
 * Llama directamente a la API de Stripe (efecto de red externo).
 */
class StripePaymentService
{
    private ?StripeClient $stripe = null;

    /**
     * Construye el cliente de Stripe solo cuando realmente se necesita
     * (no en el constructor): esta clase se inyecta en
     * Api\Payment\PaymentController, cuyo contenedor la resuelve para
     * CUALQUIER accion de ese controlador (index/pending/approve/...), no
     * solo las que tocan Stripe. Instanciar StripeClient de forma eager
     * revienta con "$config must be a string or an array" en cualquier
     * entorno sin STRIPE_SECRET configurado (p. ej. CI), aunque esa
     * peticion nunca use Stripe.
     */
    private function client(): StripeClient
    {
        return $this->stripe ??= new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Crea un PaymentIntent en Stripe (llamada a API externa) y devuelve
     * el client_secret que el frontend necesita para confirmar el pago.
     */
    public function createPaymentIntent(float $amount, string $currency = 'mxn', array $metadata = []): array
    {
        $intent = $this->client()->paymentIntents->create([
            // Stripe espera el monto en la unidad minima de la moneda
            // (centavos), de ahi el *100 y el redondeo a entero.
            'amount' => (int) round($amount * 100),
            'currency' => $currency,
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => $metadata,
        ]);

        return [
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
        ];
    }

    /**
     * Consulta el estado actual de un PaymentIntent en Stripe (llamada a API externa).
     */
    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Verifica si el pago se completo consultando Stripe de nuevo.
     */
    public function confirmPayment(string $paymentIntentId): bool
    {
        $intent = $this->retrievePaymentIntent($paymentIntentId);

        return $intent->status === 'succeeded';
    }

    /**
     * Inicia un reembolso total en Stripe a partir del PaymentIntent
     * original. No actualiza nada local: el webhook charge.refunded es la
     * autoridad que concilia el estado local, igual que el resto del flujo
     * de reembolsos (ver StripeWebhookController::onRefunded()).
     */
    public function refund(string $paymentIntentId): void
    {
        $this->client()->refunds->create(['payment_intent' => $paymentIntentId]);
    }

    /**
     * Crea un Product + Price recurrente mensual en Stripe para un plan de
     * membresía (ver MembershipPlan). Cada vez que cambia el precio de un
     * plan hay que llamar esto de nuevo -- los Price de Stripe son
     * inmutables una vez creados.
     */
    public function createMonthlyPrice(string $productName, float $monto, array $metadata = []): array
    {
        $product = $this->client()->products->create([
            'name' => $productName,
            'metadata' => $metadata,
        ]);

        $price = $this->client()->prices->create([
            'product' => $product->id,
            'unit_amount' => (int) round($monto * 100),
            'currency' => 'mxn',
            'recurring' => ['interval' => 'month'],
        ]);

        return [
            'product_id' => $product->id,
            'price_id' => $price->id,
        ];
    }

    /**
     * Crea (o reutiliza, si ya se le pasa un customer_id existente) el
     * Customer de Stripe asociado a un cliente -- necesario antes de poder
     * crear una Subscription.
     */
    public function createCustomer(string $email, string $name, array $metadata = []): string
    {
        return $this->client()->customers->create([
            'email' => $email,
            'name' => $name,
            'metadata' => $metadata,
        ])->id;
    }

    /**
     * Crea una Subscription en modo "default_incomplete": queda creada en
     * Stripe de inmediato pero el primer cobro requiere que el frontend
     * confirme el PaymentIntent del primer invoice con el client_secret
     * devuelto (mismo patrón SCA que el resto de los flujos de tarjeta de
     * esta app). Si el cliente nunca confirma, Stripe cancela la
     * suscripción sola pasadas ~23 horas y el webhook
     * customer.subscription.deleted la marca cancelada localmente.
     */
    public function createSubscription(string $customerId, string $priceId, array $metadata = []): array
    {
        $subscription = $this->client()->subscriptions->create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
            'payment_behavior' => 'default_incomplete',
            'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
            'expand' => ['latest_invoice.confirmation_secret', 'latest_invoice.payment_intent'],
            'metadata' => $metadata,
        ]);

        // 'expand' de arriba garantiza que latest_invoice venga como objeto
        // (no como el ID string que Stripe devuelve por defecto) -- toArray()
        // recursivo evita depender de las propiedades tipadas del SDK, que no
        // cubren todos los campos expandibles. Esta cuenta de Stripe ya está
        // en la versión de API que reemplazó invoice.payment_intent por
        // invoice.confirmation_secret (verificado en vivo: el primer campo
        // simplemente no existe en la respuesta) -- se intenta el nuevo
        // primero y el viejo como respaldo por si la cuenta cambia de
        // versión de API más adelante.
        $invoice = $subscription->latest_invoice instanceof Invoice ? $subscription->latest_invoice->toArray() : [];

        return [
            'subscription_id' => $subscription->id,
            'client_secret' => $invoice['confirmation_secret']['client_secret']
                ?? $invoice['payment_intent']['client_secret']
                ?? null,
        ];
    }

    /**
     * Marca la suscripción para cancelarse al terminar el periodo ya
     * pagado, en vez de cortar el beneficio de inmediato (decisión de
     * negocio: el cliente sigue disfrutando lo que ya pagó).
     */
    public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
    {
        $this->client()->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);
    }
}
