<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use App\Services\Payment\StripePaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * API de pagos para personal de recepción/administración (móvil y web).
 * Maneja registro, consulta, eliminación y generación de comprobantes (PDF)
 * de pagos asociados a citas, incluyendo integración con Stripe.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly StripePaymentService $stripeService,
    ) {}

    /**
     * Lista paginada de pagos con datos de cita, cliente, barbero y comprobante,
     * filtrable por método de pago.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $filters = $request->only(['metodo_pago']);
        $payments = $this->paymentService->list($filters, 50);

        return response()->json([
            'data' => collect($payments->items())->map(fn ($payment) => [
                'id' => $payment->id,
                'monto' => $payment->monto,
                'metodo_pago' => $payment->metodo_pago,
                'propina' => $payment->propina,
                'receipt_url' => $payment->comprobante_pdf ? Storage::disk('public')->url($payment->comprobante_pdf) : null,
                'created_at' => optional($payment->created_at)->toIso8601String(),
                'appointment' => [
                    'id' => $payment->appointment?->id,
                    'fecha' => optional($payment->appointment?->fecha)->toDateString(),
                    'hora_inicio' => $payment->appointment?->hora_inicio,
                    'service' => $payment->appointment?->service?->nombre,
                    'client' => $payment->appointment?->client?->user?->name,
                    'barber' => $payment->appointment?->barber?->user?->name,
                ],
                'creator' => [
                    'id' => $payment->creator?->id,
                    'name' => $payment->creator?->name,
                ],
            ])->values(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Crea un PaymentIntent en Stripe (moneda fija MXN) para que el cliente
     * pague desde la app; no registra el pago local, eso ocurre vía webhook o store().
     */
    public function stripeIntent(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01'],
            'appointment_id' => ['required', 'string', 'exists:appointments,id'],
        ]);

        try {
            $data = $this->stripeService->createPaymentIntent(
                (float) $validated['monto'],
                'mxn',
                ['appointment_id' => $validated['appointment_id']]
            );

            return response()->json(['data' => $data]);
        } catch (Throwable $exception) {
            // No se expone el detalle de Stripe al cliente; se registra para diagnóstico
            Log::warning('No se pudo crear intento de pago Stripe.', [
                'appointment_id' => $validated['appointment_id'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo crear el intento de pago Stripe. Intenta de nuevo o usa otro método de pago.',
            ], 422);
        }
    }

    /**
     * Registra un pago manual (efectivo, tarjeta, transferencia, QR o stripe)
     * asociado a una cita; la lógica de negocio (transacción, estado de cita) vive en PaymentService.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'appointment_id' => ['required', 'string', 'exists:appointments,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia,qr,stripe'],
            'propina' => ['nullable', 'numeric', 'min:0'],
            'stripe_payment_id' => ['nullable', 'string'],
        ]);

        $payment = $this->paymentService->create($validated, (string) $request->user()->id);

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data' => [
                'id' => $payment->id,
                'monto' => $payment->monto,
                'metodo_pago' => $payment->metodo_pago,
                'propina' => $payment->propina,
                'receipt_url' => $payment->comprobante_pdf ? Storage::disk('public')->url($payment->comprobante_pdf) : null,
                'appointment' => [
                    'id' => $payment->appointment?->id,
                    'fecha' => optional($payment->appointment?->fecha)->toDateString(),
                    'service' => $payment->appointment?->service?->nombre,
                ],
            ],
        ], 201);
    }

    /**
     * Elimina un pago y su comprobante PDF asociado (si existe) del almacenamiento público.
     */
    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        if ($payment->comprobante_pdf) {
            Storage::disk('public')->delete($payment->comprobante_pdf);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Pago eliminado correctamente.',
        ]);
    }

    /**
     * Devuelve la URL del comprobante PDF; lo genera bajo demanda (lazy) y lo cachea
     * en el pago si aún no existe o el archivo se perdió del disco.
     */
    public function receipt(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        $pdfPath = $payment->comprobante_pdf;

        if (! $pdfPath || ! Storage::disk('public')->exists($pdfPath)) {
            $pdf = Pdf::loadView('payments.receipt', [
                'payment' => $payment->load(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator']),
            ]);

            $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
            Storage::disk('public')->put($pdfPath, $pdf->output());

            $payment->update(['comprobante_pdf' => $pdfPath]);
        }

        return response()->json([
            'data' => [
                'payment_id' => $payment->id,
                'receipt_url' => Storage::disk('public')->url($pdfPath),
            ],
        ]);
    }

    /**
     * Restringe el acceso a administradores y recepcionistas; el resto de roles recibe 403.
     */
    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}
