<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: firma inválida', ['error' => $e->getMessage()]);

            return response('Firma inválida', 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook: payload inválido', ['error' => $e->getMessage()]);

            return response('Payload inválido', 400);
        }

        match ($event->type) {
            'payment_intent.succeeded' => $this->onSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->onFailed($event->data->object),
            default => null,
        };

        return response('OK', 200);
    }

    private function onSucceeded(object $intent): void
    {
        $appointmentId = $intent->metadata->appointment_id ?? null;

        if (! $appointmentId) {
            return;
        }

        $appointment = Appointment::find($appointmentId);
        if (! $appointment) {
            Log::warning("Stripe webhook: cita {$appointmentId} no encontrada");

            return;
        }

        // Marcar la cita como completada si aún está pendiente
        if ($appointment->estado === 'confirmada' || $appointment->estado === 'pendiente') {
            $appointment->update(['estado' => 'completada']);
        }

        // Registrar el pago si no existe ya con este payment_intent_id
        $alreadyPaid = Payment::where('stripe_payment_id', $intent->id)->exists();

        if (! $alreadyPaid) {
            Payment::create([
                'appointment_id' => $appointmentId,
                'monto' => $intent->amount / 100,
                'propina' => 0,
                'metodo_pago' => 'stripe',
                'stripe_payment_id' => $intent->id,
                'created_by' => null, // pago automático vía webhook
            ]);

            Log::info("Stripe webhook: pago {$intent->id} registrado para cita {$appointmentId}");
        }
    }

    private function onFailed(object $intent): void
    {
        $appointmentId = $intent->metadata->appointment_id ?? null;
        $reason = $intent->last_payment_error?->message ?? 'desconocido';

        Log::warning("Stripe webhook: pago fallido para cita {$appointmentId}", [
            'payment_intent_id' => $intent->id,
            'reason' => $reason,
        ]);
    }
}
