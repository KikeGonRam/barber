<?php

namespace App\Services\Payment;

use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripePaymentService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createPaymentIntent(float $amount, string $currency = 'mxn', array $metadata = []): array
    {
        $intent = $this->stripe->paymentIntents->create([
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

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    public function confirmPayment(string $paymentIntentId): bool
    {
        $intent = $this->retrievePaymentIntent($paymentIntentId);

        return $intent->status === 'succeeded';
    }
}
