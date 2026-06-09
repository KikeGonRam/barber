<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    public function index(\Illuminate\Http\Request $request): View
    {
        $filters = $request->only(['q', 'metodo_pago', 'fecha_desde', 'fecha_hasta', 'barbero_id']);

        $payments = Payment::query()
            ->with(['appointment.client.user', 'appointment.barber.user', 'appointment.service', 'creator'])
            ->when(!empty($filters['q']), function ($query) use ($filters) {
                $q = $filters['q'];
                $query->whereHas('appointment.client.user', fn($u) => $u->where('name', 'like', "%{$q}%"))
                      ->orWhereHas('appointment.service', fn($s) => $s->where('nombre', 'like', "%{$q}%"));
            })
            ->when(!empty($filters['metodo_pago']), fn($q) => $q->where('metodo_pago', $filters['metodo_pago']))
            ->when(!empty($filters['barbero_id']), fn($q) => $q->whereHas('appointment', fn($a) => $a->where('barber_id', $filters['barbero_id'])))
            ->when(!empty($filters['fecha_desde']), fn($q) => $q->whereDate('created_at', '>=', $filters['fecha_desde']))
            ->when(!empty($filters['fecha_hasta']), fn($q) => $q->whereDate('created_at', '<=', $filters['fecha_hasta']))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $barbers = \App\Models\Barber::with('user:id,name')->where('activo', true)->get(['id', 'user_id']);
        $stats = [
            'total_hoy'    => (float) Payment::whereBetween('created_at', [now()->startOfDay(), now()->endOfDay()])->get(['monto', 'propina'])->sum(fn($p) => (float)$p->monto + (float)$p->propina),
            'total_mes'    => (float) Payment::whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->get(['monto', 'propina'])->sum(fn($p) => (float)$p->monto + (float)$p->propina),
            'count'        => Payment::count(),
            'metodos'      => Payment::get(['metodo_pago'])->groupBy('metodo_pago')->map->count(),
        ];

        return view('payments.index', compact('payments', 'filters', 'barbers', 'stats'));
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
            $this->paymentService->create($request->validated(), (string) $request->user()->id);
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
