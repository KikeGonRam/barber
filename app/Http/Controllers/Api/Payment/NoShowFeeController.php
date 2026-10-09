<?php

namespace App\Http\Controllers\Api\Payment;

use App\Exceptions\Domain\PaymentException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\NoShowFee;
use App\Services\Payment\NoShowFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cargos por inasistencia (ver NoShowFeeService). El cliente ve los suyos; recepción y administración ven los
 * pendientes y los cobran en sucursal; solo administración los condona.
 */
class NoShowFeeController extends Controller
{
    public function __construct(private readonly NoShowFeeService $fees) {}

    /**
     * Listar cargos por inasistencia
     *
     * Cliente: los suyos (con el total adeudado). Recepción/administración: por estado (pendiente por defecto) y,
     * opcionalmente, de un cliente.
     *
     * @authenticated
     *
     * @queryParam estado string pendiente|pagado|condonado (solo personal). Example: pendiente
     * @queryParam client_id string Filtra por cliente (solo personal).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = NoShowFee::query()->orderByDesc('created_at');

        if ($user && $user->hasAnyRole(['administrador', 'recepcionista'])) {
            $query->where('estado', (string) $request->query('estado', NoShowFee::ESTADO_PENDIENTE));
            if ($clientId = $request->query('client_id')) {
                $query->where('client_id', (string) $clientId);
            }
            $adeudoTotal = null;
        } elseif ($user && $user->hasRole('cliente') && $user->clientProfile) {
            $clientId = (string) $user->clientProfile->id;
            $query->where('client_id', $clientId);
            $adeudoTotal = $this->fees->outstandingTotal($clientId);
        } else {
            abort(403, 'No autorizado.');
        }

        $fees = $query->limit(100)->get();
        $appointments = Appointment::query()
            ->with(['service', 'client.user'])
            ->whereIn('_id', $fees->pluck('appointment_id')->all())
            ->get()
            ->keyBy(fn (Appointment $a): string => (string) $a->id);

        return response()->json([
            'data' => $fees->map(fn (NoShowFee $fee): array => $this->present($fee, $appointments->get((string) $fee->appointment_id)))->values(),
            'adeudo_total' => $adeudoTotal,
        ]);
    }

    /**
     * Cobrar un cargo en sucursal
     *
     * @authenticated
     *
     * @bodyParam metodo string required efectivo|transferencia. Example: efectivo
     */
    public function pay(Request $request, NoShowFee $fee): JsonResponse
    {
        $validated = $request->validate(['metodo' => ['required', 'in:efectivo,transferencia']]);

        try {
            $fee = $this->fees->markPaid($fee, $validated['metodo'], (string) $request->user()?->id);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Cargo cobrado.', 'data' => $this->present($fee, null)]);
    }

    /**
     * Condonar un cargo (solo administración)
     *
     * @authenticated
     *
     * @bodyParam motivo string required Por qué se perdona el cargo. Example: Cliente con emergencia médica.
     */
    public function waive(Request $request, NoShowFee $fee): JsonResponse
    {
        abort_unless($request->user()?->hasRole('administrador'), 403, 'Solo administración puede condonar un cargo.');

        $validated = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:300']]);

        try {
            $fee = $this->fees->waive($fee, (string) $request->user()->id, $validated['motivo']);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Cargo condonado.', 'data' => $this->present($fee, null)]);
    }

    /** @return array<string, mixed> */
    private function present(NoShowFee $fee, ?Appointment $appointment): array
    {
        return [
            'id' => (string) $fee->id,
            'appointment_id' => (string) $fee->appointment_id,
            'cita' => $appointment ? [
                'code' => $appointment->getAttribute('code'),
                'fecha' => optional($appointment->fecha)->toDateString(),
                'servicio' => $appointment->service?->getAttribute('nombre'),
                'cliente' => data_get($appointment, 'client.user.name'),
            ] : null,
            'client_id' => (string) $fee->client_id,
            'monto' => (float) $fee->monto,
            'monto_base' => (float) $fee->monto_base,
            'credito_anticipo' => (float) $fee->credito_anticipo,
            'porcentaje' => (int) $fee->porcentaje,
            'estado' => $fee->estado,
            'metodo_cobro' => $fee->metodo_cobro,
            'motivo' => $fee->motivo,
            'cobrado_en' => $fee->cobrado_en?->toIso8601String(),
        ];
    }
}
