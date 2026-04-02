<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

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

    public function store(Request $request): JsonResponse
    {
        $this->authorizeStaff($request);

        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'metodo_pago' => ['required', 'in:efectivo,tarjeta,transferencia,qr'],
            'propina' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payment = $this->paymentService->create($validated, (int) $request->user()->id);

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

    private function authorizeStaff(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');
    }
}