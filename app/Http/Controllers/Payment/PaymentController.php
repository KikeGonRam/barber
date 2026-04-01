<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(): View
    {
        $payments = $this->paymentService->list(request()->only(['metodo_pago']));

        return view('payments.index', compact('payments'));
    }

    public function create(): View
    {
        $appointments = Appointment::query()
            ->whereNotIn('estado', ['cancelada', 'no_asistio'])
            ->whereDoesntHave('payments')
            ->with(['client.user', 'barber.user', 'service'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->get();

        return view('payments.create', compact('appointments'));
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        try {
            $this->paymentService->create($request->validated(), (int) $request->user()->id);
        } catch (PaymentException $exception) {
            return back()->withInput()->withErrors(['appointment_id' => $exception->getMessage()]);
        }

        return redirect()->route('payments.index')->with('status', 'Pago registrado correctamente.');
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        if ($payment->comprobante_pdf) {
            Storage::disk('public')->delete($payment->comprobante_pdf);
        }

        $payment->delete();

        return redirect()->route('payments.index')->with('status', 'Pago eliminado correctamente.');
    }

    public function downloadReceipt(Payment $payment)
    {
        $pdfPath = $payment->comprobante_pdf;

        // Generate on demand if file is missing
        if (! $pdfPath || ! Storage::disk('public')->exists($pdfPath)) {
            $pdf = Pdf::loadView('payments.receipt', [
                'payment' => $payment->load(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator']),
            ]);

            $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
            Storage::disk('public')->put($pdfPath, $pdf->output());

            $payment->update(['comprobante_pdf' => $pdfPath]);
        }

        $stamp = optional($payment->created_at)->format('Ymd-His') ?: now()->format('Ymd-His');

        return Storage::disk('public')->download($pdfPath, 'factura-'.$payment->id.'-'.$stamp.'.pdf');
    }
}
