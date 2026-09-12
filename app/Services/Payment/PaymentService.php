<?php

namespace App\Services\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Jobs\RunOcrOnComprobante;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Payment;
use App\Notifications\Payment\PaymentReceiptNotification;
use App\Notifications\Payment\TransferReceiptNotification;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Appointment\AppointmentNotifier;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Loyalty\LoyaltyService;
use App\Services\Loyalty\RaffleService;
use App\Services\Loyalty\ReferralService;
use App\Services\Membership\MembershipService;
use App\Services\Package\GiftCardService;
use App\Services\Package\PackageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MongoDB\Driver\Exception\BulkWriteException;

/**
 * Orquesta el cobro de citas: pagos directos por staff, subida y revision
 * de comprobantes de transferencia, y el "completar cobro" compartido
 * (marca cita completada, genera PDF de recibo y notifica al cliente).
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly AppointmentNotifier $notifier,
        private readonly LoyaltyService $loyalty,
        private readonly RaffleService $raffle,
        private readonly DepositService $deposits,
        private readonly PackageService $packages,
        private readonly GiftCardService $giftCards,
        private readonly ReferralService $referrals,
        private readonly MembershipService $memberships,
    ) {}

    /**
     * Aplica el mayor de dos descuentos -- por nivel de lealtad o por
     * membresía recurrente activa -- nunca ambos sumados (decisión de
     * negocio: la membresía es un beneficio alternativo, no acumulable).
     */
    private function applyBestDiscount(float $precioBase, ?Client $client): float
    {
        $membershipPct = $client ? $this->memberships->activeDiscountFor($client) : 0;
        $pct = LoyaltyService::bestDiscountPct($client?->nivel ?? 'nuevo', $membershipPct);

        return $pct > 0 ? round($precioBase * (1 - $pct / 100), 2) : $precioBase;
    }

    /**
     * Lista pagos paginados aplicando filtros del repositorio.
     */
    public function list(array $filters = [], int $perPage = 15)
    {
        return $this->payments->paginateWithFilters($filters, $perPage);
    }

    /**
     * Cobro directo por staff: crea el pago ya verificado y completa el
     * cobro (cita completada + PDF + notificacion) dentro de una
     * transaccion DB para evitar estados intermedios inconsistentes.
     */
    public function create(array $payload, ?string $createdBy): Payment
    {
        $appointment = Appointment::query()->with(['client.user', 'barber.user', 'service'])->findOrFail($payload['appointment_id']);

        // Gate de cobro: solo citas aprobadas por el barbero (nunca pendiente).
        if (! in_array($appointment->estado, AppointmentStatusService::CHARGEABLE, true)) {
            throw new PaymentException('Solo se puede cobrar una cita aprobada por el barbero (confirmada, en proceso o completada). Esta cita esta en estado: '.$appointment->estado.'.');
        }

        if ($this->payments->existsForAppointment((string) $appointment->id)) {
            throw new PaymentException('La cita ya tiene un pago registrado.');
        }

        return DB::transaction(function () use ($payload, $createdBy, $appointment) {
            $client = $appointment->client;
            $puntosCanjeados = (int) ($payload['puntos_canjeados'] ?? 0);
            $usarPremioRifa = (bool) ($payload['usar_premio_rifa'] ?? false);
            $usarPaqueteId = $payload['usar_paquete_id'] ?? null;
            $codigoGiftCard = $payload['codigo_gift_card'] ?? null;

            if (($usarPremioRifa || $usarPaqueteId) && $puntosCanjeados > 0) {
                throw new PaymentException('No puedes canjear puntos junto con el premio de la rifa o un paquete en el mismo cobro.');
            }

            if ($usarPremioRifa && $usarPaqueteId) {
                throw new PaymentException('No puedes usar el premio de la rifa y un paquete en el mismo cobro.');
            }

            if ($codigoGiftCard && ($usarPremioRifa || $usarPaqueteId)) {
                throw new PaymentException('No puedes usar una tarjeta de regalo junto con el premio de la rifa o un paquete: ambos ya cubren el cobro completo.');
            }

            $premioRifa = null;
            $clientPackage = null;

            if ($usarPaqueteId) {
                $clientPackage = ClientPackage::findOrFail($usarPaqueteId);
                $this->packages->redeem($clientPackage, $appointment);

                // El paquete ya cubrió este uso del servicio: cobro en $0,
                // igual criterio que el premio de rifa.
                $monto = 0.0;
            } elseif ($usarPremioRifa) {
                if (! $client) {
                    throw new PaymentException('No se puede aplicar el premio de la rifa: esta cita no tiene un cliente asociado.');
                }

                $premioRifa = $this->raffle->activePrizeFor($client);
                if (! $premioRifa) {
                    throw new PaymentException('Este cliente no tiene un premio de rifa disponible para reclamar.');
                }

                // El premio cubre el servicio completo: no se combina con el
                // descuento de nivel ni con puntos (ya validado arriba).
                $monto = 0.0;
            } else {
                // El precio base NUNCA se toma de $payload['monto'] (viene de un
                // formulario web, no hay que confiar en el en un cobro real) —
                // siempre se relee del servicio de la cita, igual que ya hacen
                // uploadTransferReceipt() y el intent de Stripe. El campo "Monto
                // del Servicio" del formulario es solo informativo/legado.
                $precioBase = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
                $monto = $this->applyBestDiscount($precioBase, $client);

                if ($puntosCanjeados > 0) {
                    if (! $client) {
                        throw new PaymentException('No se pueden canjear puntos: esta cita no tiene un cliente asociado.');
                    }

                    $maxCanjeable = LoyaltyService::maxRedeemablePoints($monto, (int) $client->puntos);
                    if ($puntosCanjeados > $maxCanjeable) {
                        throw new PaymentException("Solo se pueden canjear hasta {$maxCanjeable} puntos en este cobro (tope: 50% del total o el saldo disponible del cliente).");
                    }

                    if (! $this->loyalty->redeemPoints($client, $puntosCanjeados, 'Canje aplicado al cobro de la cita '.$appointment->id)) {
                        throw new PaymentException('No se pudieron canjear los puntos indicados.');
                    }

                    // 1 punto = $1 MXN, ya validado contra el tope de arriba.
                    $monto -= $puntosCanjeados;
                }

                // Si esta cita ya tuvo un depósito anti-no-show verificado
                // (ver DepositService), se resta del cobro final: el cliente
                // ya pagó esa parte al reservar, no se le vuelve a cobrar.
                // Nunca queda negativo (clamp a 0) ni se maneja el excedente
                // aquí -- un depósito no puede ser mayor al total con
                // descuento porque siempre es un % del precio base.
                $monto = max(0.0, $monto - $this->deposits->verifiedAmountFor($appointment));
            }

            $giftCard = null;
            $giftCardAplicado = 0.0;

            if ($codigoGiftCard) {
                $giftCard = $this->giftCards->findRedeemable($codigoGiftCard);
                if (! $giftCard) {
                    throw new PaymentException('El código de la tarjeta de regalo no es válido o ya no tiene saldo.');
                }

                // apply() acepta parcial: si el saldo no alcanza para todo
                // el cobro, solo se descuenta lo que sí cubre y el resto se
                // paga por metodo_pago normal (no es todo-o-nada como el
                // paquete/premio de rifa).
                //
                // Mismo cruce que el catch (BulkWriteException) de más abajo
                // (webhook de Stripe vs. POST directo de staff para la MISMA
                // cita con tarjeta) puede aterrizar aquí primero: el
                // decrement() de GiftCardService::apply() es lo primero que
                // escribe en la transacción, antes de llegar al índice único
                // de Payment. Sin este catch, el perdedor de la carrera
                // recibía el BulkWriteException crudo de Mongo en vez de un
                // mensaje claro. Si en cambio el saldo genuinamente no
                // alcanza (sin relación con esta carrera), apply() ya lanza
                // GiftCardException con su propio mensaje correcto -- no se
                // captura aquí para no ocultarlo detrás de un mensaje de
                // "pago ya registrado" que sería engañoso en ese caso.
                try {
                    $giftCardAplicado = $this->giftCards->apply($giftCard, $monto);
                } catch (BulkWriteException $e) {
                    throw new PaymentException('La cita ya tiene un pago registrado.');
                }
                $monto = round($monto - $giftCardAplicado, 2);
            }

            try {
                // gift_card_id/gift_card_monto_aplicado (y por el mismo
                // motivo, en teoria, raffle_result_id/client_package_id) NO
                // se incluyen en el array cuando no aplican -- pasar null
                // explicito a un campo con cast decimal:2 revienta el cast
                // en create() (Laravel intenta BigDecimal::of((string) null)
                // = BigDecimal::of("") y truena con MathException), a
                // diferencia de sencillamente omitir la clave. Mismo motivo
                // por el que ocr_monto_detectado nunca se manda en el
                // create() inicial en otro punto de este archivo.
                $paymentData = [
                    'appointment_id' => $payload['appointment_id'],
                    'monto' => $monto,
                    'metodo_pago' => $payload['metodo_pago'],
                    'propina' => $payload['propina'] ?? 0,
                    'created_by' => $createdBy,
                    'estado' => Payment::ESTADO_VERIFICADO,
                    'puntos_canjeados' => $puntosCanjeados,
                    'stripe_payment_id' => $payload['stripe_payment_id'] ?? null,
                ];

                if ($premioRifa) {
                    $paymentData['raffle_result_id'] = $premioRifa->id;
                }

                if ($clientPackage) {
                    $paymentData['client_package_id'] = $clientPackage->id;
                }

                if ($giftCard) {
                    $paymentData['gift_card_id'] = $giftCard->id;
                    $paymentData['gift_card_monto_aplicado'] = $giftCardAplicado;
                }

                $payment = $this->payments->create($paymentData);
            } catch (BulkWriteException $e) {
                // existsForAppointment() de arriba no es atomico: si dos
                // requests para la misma cita llegan a la vez (p.ej. un
                // reintento de webhook de Stripe cruzando un doble-click de
                // "cobrar"), el indice unico parcial (appointment_id, solo
                // pagos no rechazados) es la garantia real. Los puntos ya
                // canjeados arriba se revierten al hacer rollback la
                // transaccion completa -- no queda a medias.
                throw new PaymentException('La cita ya tiene un pago registrado.');
            }

            if ($premioRifa) {
                $this->raffle->claim($premioRifa, $appointment);
            }

            return $this->completeCharge($payment, $appointment, $monto);
        });
    }

    /**
     * El cliente sube su comprobante de transferencia. Queda en revision:
     * NO marca la cita como completada ni genera factura todavia. Avisa a
     * recepcion/admin para que lo revisen.
     */
    public function uploadTransferReceipt(Appointment $appointment, UploadedFile $file, string $clientUserId): Payment
    {
        if (! in_array($appointment->estado, AppointmentStatusService::CHARGEABLE, true)) {
            throw new PaymentException('Solo se puede subir comprobante de una cita aprobada por el barbero.');
        }

        if ($this->payments->existsForAppointment((string) $appointment->id)) {
            throw new PaymentException('La cita ya tiene un pago registrado o en revision.');
        }

        $path = $file->store('comprobantes-transferencia', 'public');

        // Mismo calculo que ClientPaymentController::create() ya le mostro al
        // cliente antes de que transfiriera, para que el monto registrado
        // coincida exactamente con lo que se le pidio transferir.
        $precioBase = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
        $monto = $this->applyBestDiscount($precioBase, $appointment->client);

        try {
            $payment = $this->payments->create([
                'appointment_id' => (string) $appointment->id,
                'monto' => $monto,
                'metodo_pago' => 'transferencia',
                'propina' => 0,
                'created_by' => $clientUserId,
                'estado' => Payment::ESTADO_PENDIENTE_VERIFICACION,
                'comprobante_cliente' => $path,
            ]);
        } catch (BulkWriteException $e) {
            // Mismo respaldo que create(): existsForAppointment() de arriba
            // no es atomico.
            throw new PaymentException('La cita ya tiene un pago registrado o en revision.');
        }

        $appointment->loadMissing(['client.user', 'service']);

        // Encola un job asincrono para leer el comprobante via OCR (ayuda a
        // recepcion/admin a verificarlo, no bloquea la subida).
        RunOcrOnComprobante::dispatch((string) $payment->id);

        // Avisa a recepcion/admin (canal interno) que hay un comprobante por revisar.
        $this->notifier->transferReceiptUploaded($appointment, $payment);

        if ($user = $appointment->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'recibido'));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de comprobante recibido', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Aprueba un comprobante en revision y completa el cobro. Actualiza
     * estado dentro de transaccion DB y luego reusa completeCharge() para
     * marcar la cita, generar el PDF y notificar.
     */
    public function approveTransfer(Payment $payment, string $reviewerId): Payment
    {
        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $appointment = Appointment::query()->with(['client.user', 'barber.user', 'service'])->findOrFail($payment->appointment_id);

        return DB::transaction(function () use ($payment, $appointment, $reviewerId) {
            $this->payments->update($payment->id, [
                'estado' => Payment::ESTADO_VERIFICADO,
                'revisado_por' => $reviewerId,
                'revisado_en' => now(),
            ]);

            $payment = $payment->fresh();

            return $this->completeCharge($payment, $appointment, (float) $payment->monto);
        });
    }

    /**
     * Rechaza un comprobante en revision (no completa el cobro) y notifica
     * al cliente el motivo para que pueda volver a subir un comprobante.
     */
    public function rejectTransfer(Payment $payment, string $reviewerId, string $motivo): Payment
    {
        if ($payment->estado !== Payment::ESTADO_PENDIENTE_VERIFICACION) {
            throw new PaymentException('Este comprobante ya fue revisado.');
        }

        $this->payments->update($payment->id, [
            'estado' => Payment::ESTADO_RECHAZADO,
            'revisado_por' => $reviewerId,
            'revisado_en' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        $payment = $payment->fresh(['appointment.client.user']);

        if ($user = $payment->appointment?->client?->user) {
            try {
                $user->notify(new TransferReceiptNotification($payment, 'rechazado', $motivo));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificacion de comprobante rechazado', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }

    /**
     * Logica compartida de "completar el cobro": marca la cita completada,
     * genera la factura PDF y notifica al cliente. Usada tanto por el flujo
     * directo de staff (create) como por la aprobacion de transferencia.
     */
    private function completeCharge(Payment $payment, Appointment $appointment, float $monto): Payment
    {
        // 'completada' ya es un estado cobrable (CHARGEABLE incluye
        // 'completada' para permitir cobrar una cita que el barbero ya
        // marco como terminada) — hay que capturar el estado ANTES de
        // actualizarlo para no otorgar puntos de lealtad dos veces si la
        // cita ya habia sido completada por otra via (agenda del barbero o
        // el dropdown de estado de recepcion/admin).
        $wasCompletada = $appointment->estado === 'completada';

        // Efecto secundario: transiciona la cita a "completada" (fin del
        // flujo de estados) y fija el precio realmente cobrado.
        $appointment->update([
            'estado' => 'completada',
            'precio_cobrado' => $monto,
        ]);

        // Otorga puntos de lealtad la primera vez que la cita se completa.
        // Antes solo pasaba si se completaba desde el dropdown de estado o
        // la agenda del barbero; el flujo de cobro (el mas comun en la
        // practica) nunca lo disparaba.
        if (! $wasCompletada) {
            $client = $appointment->client;
            if ($client) {
                $this->loyalty->awardCitaPoints($client, (string) $appointment->id);

                // Si este cliente fue referido y esta es su primera cita
                // completada, aquí es donde se le paga la recompensa a
                // quien lo trajo (ver ReferralService::completeIfEligible()).
                $this->referrals->completeIfEligible($client);
            }
        }

        // Genera el PDF del recibo/factura con DomPDF a partir de una vista Blade.
        $pdf = Pdf::loadView('payments.receipt', [
            'payment' => $payment->load(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator']),
        ]);

        // Persiste el PDF en el disco publico para poder servirlo despues.
        $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $this->payments->update($payment->id, ['comprobante_pdf' => $pdfPath]);

        $payment = $payment->fresh(['appointment.client.user']);

        $user = $payment->appointment?->client?->user;

        // Notifica al cliente el recibo de pago; el fallo de notificacion
        // se registra pero no revierte el cobro ya completado.
        if ($user) {
            try {
                $user->notify(new PaymentReceiptNotification($payment));
            } catch (\Throwable $e) {
                Log::warning('Fallo notificación comprobante de pago', [
                    'payment_id' => $payment->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $payment;
    }
}
