<?php

namespace App\Services\Payment;

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
}
