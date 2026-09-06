<?php

namespace App\Http\Controllers\Api\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Appointment\AppointmentNotifier;
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
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly AppointmentNotifier $notifier,
    ) {}

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
            'charge.refunded' => $this->onRefunded($event->data->object),
            'charge.dispute.created' => $this->onDisputeCreated($event->data->object),
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
     * Al fallar el pago en Stripe: no se modifica el estado de la cita ni
     * se crea un Payment (nada que conciliar), pero sí se avisa a
     * recepción/admin -- antes esto solo quedaba en el log, así que un
     * cobro fallido pasaba inadvertido hasta que alguien revisara Stripe
     * directamente o el cliente se quejara de no poder pagar.
     */
    private function onFailed(object $intent): void
    {
        $appointmentId = $intent->metadata->appointment_id ?? null;
        $reason = $intent->last_payment_error?->message ?? 'desconocido';

        Log::warning("Stripe webhook: pago fallido para cita {$appointmentId}", [
            'payment_intent_id' => $intent->id,
            'reason' => $reason,
        ]);

        $appointment = $appointmentId ? Appointment::find($appointmentId) : null;
        if (! $appointment) {
            return;
        }

        $appointment->loadMissing('client.user');
        $cliente = $appointment->client?->user?->name ?? 'un cliente';

        $this->notifier->sendStaff(
            $appointment,
            'Pago con tarjeta fallido',
            'Un cobro con tarjeta falló',
            "El cobro con tarjeta de {$cliente} falló ({$reason}). Puede reintentarse desde el formulario de pagos u ofrecer otro método.",
            '#ef4444',
            'Fallido',
        );
    }

    /**
     * Stripe reporta un reembolso (total o parcial) sobre un cargo ya
     * cobrado. No revierte automáticamente puntos de lealtad ni el estado
     * de la cita -- eso implica decisiones de negocio (¿se revierte
     * siempre, incluso en un reembolso parcial? ¿la cita vuelve a
     * "pendiente"?) que no están definidas todavía; por ahora solo deja
     * rastro visible (log + aviso a staff) para que alguien lo resuelva a
     * mano, en vez de que el reembolso pase completamente inadvertido
     * (que es el comportamiento de hoy: ningún evento de Stripe fuera de
     * succeeded/failed se procesa en absoluto).
     */
    private function onRefunded(object $charge): void
    {
        $paymentIntentId = $charge->payment_intent ?? null;
        $payment = $paymentIntentId ? Payment::where('stripe_payment_id', $paymentIntentId)->first() : null;

        Log::warning('Stripe webhook: reembolso recibido', [
            'payment_intent_id' => $paymentIntentId,
            'amount_refunded' => $charge->amount_refunded ?? null,
            'local_payment_id' => $payment?->id,
        ]);

        if (! $payment) {
            return;
        }

        $payment->loadMissing('appointment.client.user');
        $appointment = $payment->appointment;
        if (! $appointment) {
            return;
        }

        $cliente = $appointment->client?->user?->name ?? 'un cliente';
        $montoReembolsado = number_format(($charge->amount_refunded ?? 0) / 100, 2);

        $this->notifier->sendStaff(
            $appointment,
            'Reembolso de Stripe',
            'Se reembolsó un pago con tarjeta',
            "Stripe reembolsó \${$montoReembolsado} del pago de {$cliente}. Revisa si hace falta ajustar puntos de lealtad o el estado de la cita.",
            '#f59e0b',
            'Reembolso',
        );
    }

    /**
     * El cliente disputó (contracargo) un cobro directamente con su banco.
     * Mismo criterio que onRefunded(): solo deja rastro y avisa, sin
     * lógica automática de reversión.
     */
    private function onDisputeCreated(object $dispute): void
    {
        $paymentIntentId = $dispute->payment_intent ?? null;
        $payment = $paymentIntentId ? Payment::where('stripe_payment_id', $paymentIntentId)->first() : null;

        Log::warning('Stripe webhook: disputa/contracargo recibido', [
            'payment_intent_id' => $paymentIntentId,
            'reason' => $dispute->reason ?? 'desconocido',
            'local_payment_id' => $payment?->id,
        ]);

        if (! $payment) {
            return;
        }

        $payment->loadMissing('appointment.client.user');
        $appointment = $payment->appointment;
        if (! $appointment) {
            return;
        }

        $cliente = $appointment->client?->user?->name ?? 'un cliente';

        $this->notifier->sendStaff(
            $appointment,
            'Disputa de Stripe',
            'Un cliente disputó un cobro',
            "{$cliente} disputó su cobro con tarjeta directamente con su banco. Revisa el caso en el dashboard de Stripe.",
            '#ef4444',
            'Disputa',
        );
    }
}
