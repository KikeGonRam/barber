<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Recibe webhooks de Stripe (sin autenticación de usuario; Stripe valida por firma)
 * para conciliar pagos con PaymentIntents creados desde la app y completar citas automáticamente.
 */
class StripeWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Punto de entrada del webhook: valida la firma de Stripe y despacha según el tipo de evento.
     */
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

    /**
     * Al confirmarse el pago en Stripe: registra el pago via PaymentService
     * (mismo camino que el cobro normal de recepcion, asi que tambien marca
     * la cita completada, otorga puntos de lealtad, genera el PDF y notifica
     * al cliente).
     *
     * Es un respaldo para cuando el navegador se cierra o falla justo
     * despues de que Stripe confirma el pago pero antes de que el propio
     * formulario alcance a enviarse — normalmente ese envio del formulario ya
     * registro el pago, y PaymentService::create() rechaza con
     * PaymentException por "la cita ya tiene un pago registrado", lo cual
     * aqui es exactamente lo esperado (evita duplicar el cobro), no un error.
     */
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

        $baseMonto = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
        $puntosCanjeados = (int) ($intent->metadata->puntos_canjeados ?? 0);

        try {
            $this->paymentService->create([
                'appointment_id' => $appointmentId,
                'monto' => $baseMonto,
                'metodo_pago' => 'tarjeta',
                'propina' => 0,
                'puntos_canjeados' => $puntosCanjeados,
                'stripe_payment_id' => $intent->id,
            ], null);

            Log::info("Stripe webhook: pago {$intent->id} registrado para cita {$appointmentId}");
        } catch (PaymentException $e) {
            Log::info("Stripe webhook: pago para cita {$appointmentId} ya estaba registrado, se omite", [
                'payment_intent_id' => $intent->id,
                'reason' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Al fallar el pago en Stripe: solo se registra en log para diagnóstico,
     * no se modifica el estado de la cita ni se crea un Payment.
     */
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
