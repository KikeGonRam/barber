<?php

namespace App\Http\Controllers\Api\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Payment;
use App\Services\Payment\DepositService;
use App\Services\Payment\StripePaymentService;
use App\Support\ReceiptStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Depósitos anti-no-show
 *
 * Cobro del depósito exigido a un cliente con historial reciente de
 * inasistencias (ver DepositService), mientras su cita sigue 'pendiente' de
 * aprobación por el barbero. Vive separado de Payment\PaymentController: ese
 * controlador solo opera sobre citas ya aprobadas (CHARGEABLE).
 */
class DepositController extends Controller
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly StripePaymentService $stripe,
    ) {}

    /**
     * Crea el PaymentIntent de Stripe para el depósito de una cita propia.
     *
     * @authenticated
     *
     * También es el cobro de "pagar ahora" al reservar (pagar_ahora en
     * AppointmentController::store()). Con guardar_tarjeta o tarjeta_guardada
     * se usa el Customer de Stripe del cliente, así Stripe solo acepta sus
     * propias tarjetas y la nueva queda guardada para la próxima vez.
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     *
     * @bodyParam guardar_tarjeta boolean Guardar la tarjeta nueva para próximos pagos. Example: true
     * @bodyParam tarjeta_guardada boolean El cliente paga con una tarjeta que ya tenía guardada. Example: false
     */
    public function stripeIntent(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeOwner($request, $appointment);

        $request->validate([
            'guardar_tarjeta' => ['nullable', 'boolean'],
            'tarjeta_guardada' => ['nullable', 'boolean'],
        ]);

        try {
            $customerId = null;
            $client = $appointment->client;
            if ($client instanceof Client && ($request->boolean('guardar_tarjeta') || $request->boolean('tarjeta_guardada'))) {
                $customerId = $this->stripe->customerFor($client);
            }

            $data = $this->deposits->createStripeIntent($appointment, $customerId, $request->boolean('guardar_tarjeta'));

            return response()->json(['data' => $data]);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::warning('No se pudo crear intento de pago Stripe para deposito.', [
                'appointment_id' => (string) $appointment->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo crear el intento de pago Stripe. Intenta de nuevo o usa transferencia.',
            ], 422);
        }
    }

    /**
     * Sube el comprobante de transferencia del depósito. Queda en revisión.
     *
     * @authenticated
     *
     * @urlParam appointment string required Código público de la cita. Example: jfb7ffye
     *
     * @bodyParam comprobante file required Foto o PDF del comprobante de transferencia.
     */
    public function uploadReceipt(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorizeOwner($request, $appointment);

        $validated = $request->validate([
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        try {
            $payment = $this->deposits->uploadTransferReceipt($appointment, $validated['comprobante'], (string) $request->user()->id);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Comprobante recibido. Te avisaremos cuando se verifique.',
            'data' => ['id' => $payment->id, 'estado' => $payment->estado],
        ], 201);
    }

    /**
     * Depósitos por transferencia pendientes de revisión (staff).
     */
    public function pending(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $payments = Payment::query()
            ->where('es_deposito', true)
            ->where('estado', Payment::ESTADO_PENDIENTE_VERIFICACION)
            ->with(['appointment.client.user', 'appointment.barber.user', 'appointment.service'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $payments->map(fn ($payment) => [
                'id' => $payment->id,
                'monto' => $payment->monto,
                'created_at' => optional($payment->created_at)->toIso8601String(),
                'comprobante_url' => ReceiptStorage::url($payment->comprobante_cliente),
                'appointment' => [
                    'id' => $payment->appointment?->id,
                    'client' => $payment->appointment?->client?->user?->name,
                    'service' => $payment->appointment?->service?->nombre,
                ],
            ])->values(),
        ]);
    }

    /**
     * Aprueba un depósito en revisión (staff).
     */
    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        try {
            $payment = $this->deposits->approve($payment, (string) $request->user()->id);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Depósito aprobado.',
            'data' => ['id' => $payment->id, 'estado' => $payment->estado],
        ]);
    }

    /**
     * Rechaza un depósito en revisión (staff).
     */
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->deposits->reject($payment, (string) $request->user()->id, $validated['motivo_rechazo']);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Depósito rechazado.',
            'data' => ['id' => $payment->id, 'estado' => $payment->estado],
        ]);
    }

    private function authorizeOwner(Request $request, Appointment $appointment): void
    {
        $user = $request->user();
        $isOwner = $user?->hasRole('cliente') && $user->clientProfile && (string) $appointment->client_id === (string) $user->clientProfile->id;

        abort_unless($isOwner, 403, 'No autorizado.');
    }

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}
