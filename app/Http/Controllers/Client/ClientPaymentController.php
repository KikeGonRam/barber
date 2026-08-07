<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\Appointment\AppointmentStatusService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Subida de comprobantes de pago por transferencia (rol cliente): lista citas
 * pendientes de pago y permite adjuntar el comprobante para que recepción/admin lo valide.
 */
class ClientPaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * Citas del cliente que estan aprobadas por el barbero pero sin pago
     * registrado (o con un comprobante rechazado que puede reintentar).
     */
    public function pending(): View
    {
        $client = auth()->user()->clientProfile;
        abort_if(! $client, 403);

        $appointments = Appointment::query()
            ->where('client_id', (string) $client->id)
            ->whereIn('estado', AppointmentStatusService::CHARGEABLE)
            ->whereDoesntHave('payments', fn ($q) => $q->where('estado', '!=', Payment::ESTADO_RECHAZADO))
            ->with(['barber.user', 'service', 'payments'])
            ->orderByDesc('fecha')
            ->get();

        return view('client.payments.pending', compact('appointments'));
    }

    // Formulario de subida; muestra el último rechazo (si lo hay) para que el cliente sepa qué corregir.
    public function create(Appointment $appointment): View
    {
        $this->authorizeAppointment($appointment);

        $appointment->load(['barber.user', 'service']);

        $ultimoRechazo = $appointment->payments()->where('estado', Payment::ESTADO_RECHAZADO)->latest()->first();

        return view('client.payments.upload', compact('appointment', 'ultimoRechazo'));
    }

    // Valida el archivo (tipo MIME real, no extensión) y delega el guardado en PaymentService.
    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeAppointment($appointment);

        $request->validate([
            'comprobante' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'application/pdf'];
                    if (! in_array($value->getMimeType(), $allowed, true)) {
                        $fail('Solo se permiten imágenes (JPG, PNG, WEBP) o PDF.');
                    }
                },
            ],
        ]);

        try {
            $this->paymentService->uploadTransferReceipt($appointment, $request->file('comprobante'), (string) auth()->id());
        } catch (PaymentException $exception) {
            return back()->withErrors(['comprobante' => $exception->getMessage()]);
        }

        return redirect()->route('client.payments.pending')->with('status', 'Comprobante recibido. Te avisaremos cuando sea validado.');
    }

    // Garantiza que la cita pertenece al cliente autenticado antes de mostrar/aceptar comprobantes.
    private function authorizeAppointment(Appointment $appointment): void
    {
        $client = auth()->user()->clientProfile;
        abort_if(! $client || (string) $appointment->client_id !== (string) $client->id, 403);
    }
}
