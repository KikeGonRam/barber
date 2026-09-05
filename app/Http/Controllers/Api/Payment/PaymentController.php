<?php

namespace App\Http\Controllers\Api\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Loyalty\LoyaltyService;
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

        $filters = $request->only(['metodo_pago', 'q', 'barbero_id', 'fecha_desde', 'fecha_hasta']);
        $payments = $this->paymentService->list($filters, 20);

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
                // Estadisticas globales (no del subset filtrado/paginado actual)
                // y conteo de comprobantes por revisar, igual que la version web
                // (Payment\PaymentController::index()) para que el "Centro de
                // Facturacion" de Nuxt no tenga que hacer una segunda llamada.
                'stats' => [
                    'total_hoy' => (float) Payment::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->get(['monto', 'propina'])->sum(fn ($p) => (float) $p->monto + (float) $p->propina),
                    'total_mes' => (float) Payment::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->get(['monto', 'propina'])->sum(fn ($p) => (float) $p->monto + (float) $p->propina),
                    'count' => Payment::count(),
                    'metodos' => Payment::get(['metodo_pago'])->groupBy('metodo_pago')->map->count(),
                ],
                'pending_count' => Payment::where('estado', Payment::ESTADO_PENDIENTE_VERIFICACION)->count(),
            ],
        ]);
    }

    /**
     * Comprobantes de transferencia subidos por clientes que aun no han
     * sido aprobados/rechazados por recepcion/administracion.
     */
    public function pending(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $payments = Payment::query()
            ->where('estado', Payment::ESTADO_PENDIENTE_VERIFICACION)
            ->with(['appointment.client.user', 'appointment.barber.user', 'appointment.service'])
            ->latest()
            ->get();

        return response()->json([
            'data' => $payments->map(fn ($payment) => [
                'id' => $payment->id,
                'monto' => $payment->monto,
                'created_at' => optional($payment->created_at)->toIso8601String(),
                'comprobante_url' => $payment->comprobante_cliente ? Storage::disk('public')->url($payment->comprobante_cliente) : null,
                'ocr_texto' => $payment->ocr_texto,
                'ocr_monto_detectado' => $payment->ocr_monto_detectado,
                'appointment' => [
                    'id' => $payment->appointment?->id,
                    'client' => $payment->appointment?->client?->user?->name,
                    'service' => $payment->appointment?->service?->nombre,
                    'service_price' => $payment->appointment?->service?->precio,
                ],
            ])->values(),
        ]);
    }

    /**
     * Aprueba un comprobante de transferencia en revision: completa el
     * cobro (cita a completada, PDF de recibo, notificacion al cliente).
     */
    public function approve(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        try {
            $payment = $this->paymentService->approveTransfer($payment, (string) $request->user()->id);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Comprobante aprobado. Cita marcada como completada.',
            'data' => ['id' => $payment->id, 'estado' => $payment->estado],
        ]);
    }

    /**
     * Rechaza un comprobante de transferencia en revision con un motivo
     * obligatorio; la cita queda pendiente de que el cliente suba uno nuevo.
     */
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->paymentService->rejectTransfer($payment, (string) $request->user()->id, $validated['motivo_rechazo']);
        } catch (PaymentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Comprobante rechazado. El cliente puede subir uno nuevo.',
            'data' => ['id' => $payment->id, 'estado' => $payment->estado],
        ]);
    }

    /**
     * Crea un PaymentIntent en Stripe (moneda fija MXN) para que el cliente
     * pague desde la app; no registra el pago local, eso ocurre vía webhook o store().
     *
     * El monto NO se recibe del cliente: se calcula aqui mismo (precio base
     * del servicio -> descuento de nivel -> puntos canjeados) para que lo que
     * Stripe realmente cobra sea siempre el mismo numero que create()/el
     * webhook usaran despues para registrar el pago — nunca se confia en un
     * monto que venga del frontend para mover dinero real.
     */
    public function stripeIntent(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'appointment_id' => ['required', 'string', 'exists:appointments,id'],
            'puntos_canjeados' => ['nullable', 'integer', 'min:0'],
        ]);

        $appointment = Appointment::with('client')->findOrFail($validated['appointment_id']);
        $client = $appointment->client;
        $baseMonto = (float) ($appointment->precio_cobrado ?: $appointment->service?->precio ?? 0);
        $monto = LoyaltyService::applyDiscount($baseMonto, $client?->nivel ?? 'nuevo');

        $puntosCanjeados = (int) ($validated['puntos_canjeados'] ?? 0);
        if ($puntosCanjeados > 0) {
            $maxCanjeable = $client ? LoyaltyService::maxRedeemablePoints($monto, (int) $client->puntos) : 0;
            if ($puntosCanjeados > $maxCanjeable) {
                return response()->json([
                    'message' => "Solo se pueden canjear hasta {$maxCanjeable} puntos en este cobro.",
                ], 422);
            }
            $monto -= $puntosCanjeados;
        }

        try {
            $data = $this->stripeService->createPaymentIntent(
                $monto,
                'mxn',
                [
                    'appointment_id' => $validated['appointment_id'],
                    'puntos_canjeados' => (string) $puntosCanjeados,
                ]
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
     * Registra un pago manual (efectivo, tarjeta o transferencia) asociado a
     * una cita; la lógica de negocio (transacción, estado de cita) vive en
     * PaymentService. El pago con tarjeta vía Stripe usa stripeIntent() +
     * este mismo endpoint (con stripe_payment_id) para completarse.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'appointment_id' => ['required', 'string', 'exists:appointments,id'],
            // Con premio de rifa el cobro real es $0 (ver PaymentService::create()).
            'monto' => ['required', 'numeric', $request->boolean('usar_premio_rifa') ? 'min:0' : 'min:0.01'],
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia'],
            'propina' => ['nullable', 'numeric', 'min:0'],
            'puntos_canjeados' => ['nullable', 'integer', 'min:0'],
            'usar_premio_rifa' => ['nullable', 'boolean'],
            'stripe_payment_id' => ['required_if:metodo_pago,tarjeta', 'nullable', 'string'],
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
