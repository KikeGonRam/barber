<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Facturas/comprobantes de pago del cliente (rol cliente): historial de pagos
 * de sus citas y descarga del PDF del comprobante (generado bajo demanda si no existe aún).
 */
class ClientInvoiceController extends Controller
{
    // Historial de pagos del cliente con total pagado y total de citas.
    public function index(): View
    {
        $client = auth()->user()->clientProfile;
        abort_if(! $client, 403);

        $clientId = (string) $client->id;

        $appointmentIds = Appointment::where('client_id', $clientId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $payments = Payment::whereIn('appointment_id', $appointmentIds)
            ->with(['appointment.service', 'appointment.barber.user'])
            ->latest()
            ->paginate(12);

        $totalPagado = Payment::whereIn('appointment_id', $appointmentIds)
            ->get(['monto', 'propina'])
            ->sum(fn ($p) => (float) $p->monto + (float) $p->propina);

        $totalCitas = count($appointmentIds);

        return view('client.invoices.index', compact('payments', 'totalPagado', 'totalCitas'));
    }

    // Descarga el comprobante en PDF, generándolo y cacheándolo en storage si aún no existe.
    public function download(Payment $payment)
    {
        $client = auth()->user()->clientProfile;
        abort_if(! $client, 403);

        $appointment = $payment->appointment;
        abort_if(
            ! $appointment || (string) $appointment->client_id !== (string) $client->id,
            403
        );

        $pdfPath = $payment->comprobante_pdf;

        // Generación perezosa: el PDF se crea la primera vez que se pide, no al momento del pago.
        if (! $pdfPath || ! Storage::disk('public')->exists($pdfPath)) {
            $pdf = Pdf::loadView('payments.receipt', [
                'payment' => $payment->load([
                    'appointment.client.user',
                    'appointment.barber.user',
                    'appointment.service',
                    'creator',
                ]),
            ]);

            $pdfPath = 'comprobantes/pago-'.$payment->id.'.pdf';
            Storage::disk('public')->put($pdfPath, $pdf->output());
            $payment->update(['comprobante_pdf' => $pdfPath]);
        }

        $stamp = optional($payment->created_at)->format('Ymd-His') ?: now()->format('Ymd-His');

        return Storage::disk('public')->download($pdfPath, 'factura-'.$payment->id.'-'.$stamp.'.pdf');
    }
}
