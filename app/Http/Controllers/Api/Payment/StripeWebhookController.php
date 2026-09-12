<?php

namespace App\Http\Controllers\Api\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Models\ServicePackage;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Membership\MembershipService;
use App\Services\Package\GiftCardService;
use App\Services\Package\PackageService;
use App\Services\Payment\DepositService;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
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
        private readonly LoyaltyService $loyalty,
        private readonly DepositService $deposits,
        private readonly PackageService $packages,
        private readonly GiftCardService $giftCards,
        private readonly MembershipService $memberships,
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
            'customer.subscription.updated' => $this->onSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted($event->data->object),
            'invoice.payment_succeeded' => $this->onInvoicePaymentSucceeded($event->data->object),
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
        // Compra de paquete prepagado (ver PackageController::stripeIntent()):
        // no está ligada a ninguna cita, así que se resuelve ANTES del early
        // return por falta de appointment_id de abajo.
        if (($intent->metadata->tipo ?? null) === 'paquete') {
            $this->onPackagePurchaseSucceeded($intent);

            return;
        }

        if (($intent->metadata->tipo ?? null) === 'gift_card') {
            $this->onGiftCardPurchaseSucceeded($intent);

            return;
        }

        $appointmentId = $intent->metadata->appointment_id ?? null;

        if (! $appointmentId) {
            return;
        }

        $appointment = Appointment::find($appointmentId);
        if (! $appointment) {
            Log::warning("Stripe webhook: cita {$appointmentId} no encontrada");

            return;
        }

        // Un intent de depósito (ver DepositController::stripeIntent()) sigue
        // un camino totalmente distinto al del cobro normal: no completa la
        // cita ni otorga puntos, solo registra el depósito verificado.
        if (($intent->metadata->es_deposito ?? null) === 'true') {
            $this->deposits->confirmStripeDeposit($appointment, $intent->id, (float) $appointment->deposito_monto);
            Log::info("Stripe webhook: deposito {$intent->id} registrado para cita {$appointmentId}");

            return;
        }

        $baseMonto = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
        $puntosCanjeados = (int) ($intent->metadata->puntos_canjeados ?? 0);
        $codigoGiftCard = $intent->metadata->codigo_gift_card ?? null;

        try {
            $this->paymentService->create([
                'appointment_id' => $appointmentId,
                'monto' => $baseMonto,
                'metodo_pago' => 'tarjeta',
                'propina' => 0,
                'puntos_canjeados' => $puntosCanjeados,
                'codigo_gift_card' => $codigoGiftCard ?: null,
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
     * Confirma la compra de un paquete prepagado. Sin cita ni cliente
     * asociado no hay nada que conciliar -- se registra en el log para
     * diagnóstico, pero no se puede recuperar el dinero de metadata perdida.
     */
    private function onPackagePurchaseSucceeded(object $intent): void
    {
        $clientId = $intent->metadata->client_id ?? null;
        $servicePackageId = $intent->metadata->service_package_id ?? null;

        if (! $clientId || ! $servicePackageId) {
            Log::warning('Stripe webhook: compra de paquete sin client_id/service_package_id en metadata', ['payment_intent_id' => $intent->id]);

            return;
        }

        $client = Client::find($clientId);
        $package = ServicePackage::find($servicePackageId);

        if (! $client || ! $package) {
            Log::warning('Stripe webhook: cliente o paquete no encontrado para la compra', [
                'payment_intent_id' => $intent->id,
                'client_id' => $clientId,
                'service_package_id' => $servicePackageId,
            ]);

            return;
        }

        $this->packages->confirmStripePurchase($client, $package, $intent->id);
        Log::info("Stripe webhook: compra de paquete {$intent->id} registrada para cliente {$clientId}");
    }

    /**
     * Confirma la compra de una gift card. El comprador es opcional (una
     * gift card puede comprarla alguien sin cuenta para regalarla) --
     * a diferencia de la compra de paquete, la ausencia de client_id no es
     * un error aquí.
     */
    private function onGiftCardPurchaseSucceeded(object $intent): void
    {
        $clientId = $intent->metadata->client_id ?? null;
        $monto = (float) ($intent->metadata->monto ?? 0);

        if ($monto <= 0) {
            Log::warning('Stripe webhook: compra de gift card sin monto válido en metadata', ['payment_intent_id' => $intent->id]);

            return;
        }

        $client = $clientId ? Client::find($clientId) : null;
        $compradorNombre = $intent->metadata->comprador_nombre ?? null;
        $destinatarioEmail = $intent->metadata->destinatario_email ?? null;

        $this->giftCards->confirmStripePurchase($monto, $client, $compradorNombre ?: null, $destinatarioEmail ?: null, $intent->id);
        Log::info("Stripe webhook: compra de gift card {$intent->id} registrada");
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
     * Un reembolso total concilia puntos de forma idempotente. Los parciales
     * solo se registran y notifican para revisión humana. Nunca se restaura
     * stock ni se reabre la cita automáticamente.
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
        $refundCents = (int) ($charge->amount_refunded ?? 0);
        $paymentCents = (int) round((float) $payment->monto_total * 100);
        $fullRefund = $paymentCents > 0 && $refundCents >= $paymentCents;
        $client = $appointment->client;

        // Un depósito nunca otorgó puntos de lealtad (eso solo pasa al
        // completar el cobro final, ver PaymentService::completeCharge()),
        // así que no hay nada que reconciliar -- solo se marca reembolsado.
        // Es el propio DepositService::refundIfAny() quien inició este
        // reembolso (cancelación a tiempo); este webhook es la confirmación
        // que lo hace oficial en la base de datos, mismo criterio que el
        // resto del flujo de reembolsos.
        if ($payment->es_deposito) {
            if ($fullRefund) {
                $payment->update(['estado' => Payment::ESTADO_REEMBOLSADO]);
            }

            $montoReembolsadoDeposito = number_format($refundCents / 100, 2);
            $this->notifier->sendStaff(
                $appointment,
                'Depósito reembolsado',
                'Se reembolsó un depósito',
                "Stripe reembolsó \${$montoReembolsadoDeposito} del depósito de {$cliente} (cancelación a tiempo).",
                '#f59e0b',
                'Reembolso',
            );

            return;
        }

        if ($fullRefund && $client) {
            $this->loyalty->reconcileFullStripeRefund($payment, $client, (string) $appointment->id);
        }

        $montoReembolsado = number_format($refundCents / 100, 2);
        $reviewNote = $fullRefund
            ? 'Los puntos fueron conciliados automáticamente; revisa únicamente cualquier devolución física o ajuste operativo.'
            : 'Es un reembolso parcial: revisa manualmente si corresponde algún ajuste adicional.';

        $this->notifier->sendStaff(
            $appointment,
            'Reembolso de Stripe',
            'Se reembolsó un pago con tarjeta',
            "Stripe reembolsó \${$montoReembolsado} del pago de {$cliente}. {$reviewNote}",
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

    /**
     * Fuente de verdad de una membresía recurrente (ver MembershipService):
     * cubre tanto la activación (primer pago confirmado, status pasa de
     * 'incomplete' a 'active') como cada renovación y cada pago fallido
     * (Stripe transiciona el status a 'past_due'/'unpaid' solo, sin un
     * evento propio -- no hace falta escuchar invoice.payment_failed aparte).
     */
    private function onSubscriptionUpdated(object $subscription): void
    {
        // Esta cuenta de Stripe ya está en la versión de API que movió
        // current_period_end del objeto Subscription a cada
        // SubscriptionItem (verificado en vivo: el campo viejo llega null,
        // items.data[0].current_period_end sí lo trae) -- mismo criterio que
        // los demás campos reestructurados de esta migración, ver
        // StripePaymentService::createSubscription() y
        // onInvoicePaymentSucceeded().
        $currentPeriodEnd = $subscription->current_period_end
            ?? $subscription->items->data[0]->current_period_end
            ?? null;

        $periodoActualFin = $currentPeriodEnd !== null
            ? Carbon::createFromTimestamp($currentPeriodEnd)
            : null;

        $this->memberships->syncFromStripeStatus(
            $subscription->id,
            $subscription->status,
            $periodoActualFin,
            (bool) ($subscription->cancel_at_period_end ?? false),
        );
    }

    /**
     * La suscripción ya no existe en Stripe: cancelación efectiva al cierre
     * del periodo (cancel_at_period_end ya cumplido), o Stripe canceló sola
     * una 'incomplete' que el cliente nunca confirmó pasadas ~23 horas.
     */
    private function onSubscriptionDeleted(object $subscription): void
    {
        $this->memberships->markCancelledByStripe($subscription->id);
    }

    /**
     * Un cobro de membresía (alta o renovación) se confirmó: se registra
     * para que CashCloseService lo sume al corte del día -- este dinero
     * nunca pasa por Payment ni por el mostrador, Stripe lo cobra solo.
     * Ignora invoices que no pertenecen a ninguna suscripción (facturas
     * sueltas, si alguna vez existieran, no son de membresía).
     */
    private function onInvoicePaymentSucceeded(object $invoice): void
    {
        // Esta cuenta de Stripe ya está en la versión de API que movió
        // invoice.subscription a invoice.parent.subscription_details.subscription
        // (verificado en vivo: el campo viejo llega null) -- se intenta el
        // nuevo primero y el viejo como respaldo, mismo criterio que
        // StripePaymentService::createSubscription() con confirmation_secret.
        $subscriptionId = $invoice->parent?->subscription_details?->subscription
            ?? $invoice->subscription
            ?? null;

        if (! $subscriptionId) {
            return;
        }

        $pagadoEn = isset($invoice->status_transitions->paid_at)
            ? Carbon::createFromTimestamp($invoice->status_transitions->paid_at)
            : null;

        $this->memberships->recordSuccessfulInvoice(
            $subscriptionId,
            $invoice->id,
            ((int) ($invoice->amount_paid ?? 0)) / 100,
            $pagadoEn,
        );
    }
}
