<?php

namespace App\Http\Controllers\Api\Payment;

use App\Http\Controllers\Controller;
use App\Models\CashClose;
use App\Services\Payment\CashCloseService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Corte de caja
 *
 * Cierre de caja del día para mostrador: qué esperaba el sistema, cuánto
 * efectivo se contó y la diferencia. Administración y recepción, que es quien
 * cierra el turno.
 */
class CashCloseController extends Controller
{
    public function __construct(private readonly CashCloseService $cashCloses) {}

    private function authorizeCounterStaff(): void
    {
        abort_if(
            ! request()->user()?->hasAnyRole(['administrador', 'recepcionista']),
            403,
            'Solo administradores y recepcionistas pueden cerrar la caja.'
        );
    }

    private function parseDate(Request $request): Carbon
    {
        $request->validate(['date' => ['nullable', 'date']]);

        return Carbon::parse($request->query('date', Carbon::now()->toDateString()));
    }

    /**
     * Corte esperado
     *
     * Dinero recibido en la fecha indicada, desglosado por método de pago.
     * Suma cobros de citas (Payment verificado) y ventas de tienda (Order
     * entregado), que no generan Payment.
     *
     * @authenticated
     *
     * @queryParam date string Fecha a consultar (Y-m-d). Por defecto, hoy. Example: 2026-09-11
     */
    public function preview(Request $request): JsonResponse
    {
        $this->authorizeCounterStaff();
        $date = $this->parseDate($request);
        $expected = $this->cashCloses->expectedFor($date);

        return response()->json([
            'data' => [
                'fecha' => $date->toDateString(),
                'esperado' => $expected['por_metodo'],
                'esperado_total' => $expected['total'],
                'efectivo_esperado' => $this->cashCloses->expectedCash($expected),
                'propinas' => $expected['propinas'],
                'pagos' => $expected['pagos'],
                'pedidos' => $expected['pedidos'],
                'paquetes' => $expected['paquetes'],
                // Si ya se cerró ese día, el frontend muestra el corte hecho
                // en vez de ofrecer cerrarlo otra vez.
                'cierre' => CashClose::whereBetween('fecha', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])->first(),
            ],
        ]);
    }

    /**
     * Registrar corte
     *
     * Guarda el arqueo del día: el esperado queda como snapshot y la
     * diferencia se calcula en el servidor contra el efectivo contado, nunca
     * se acepta del cliente.
     *
     * @authenticated
     *
     * @bodyParam date string Fecha del corte (Y-m-d). Por defecto, hoy. Example: 2026-09-11
     * @bodyParam efectivo_contado number required Efectivo contado físicamente. Example: 1250.50
     * @bodyParam notas string Observaciones del cierre. Example: Faltante por cambio de un billete.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeCounterStaff();

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'efectivo_contado' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $date = Carbon::parse($validated['date'] ?? Carbon::now()->toDateString());
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        // Un corte por día: cerrar dos veces el mismo día produciría dos
        // documentos contables contradictorios.
        if (CashClose::whereBetween('fecha', [$start, $end])->exists()) {
            return response()->json([
                'message' => 'La caja de ese día ya fue cerrada.',
            ], 422);
        }

        $expected = $this->cashCloses->expectedFor($date);
        $efectivoEsperado = $this->cashCloses->expectedCash($expected);
        $contado = round((float) $validated['efectivo_contado'], 2);

        // La diferencia se calcula aquí, no se toma del request: es el dato
        // que justifica todo el arqueo (mismo criterio que el resto del
        // dinero en este proyecto, ver PaymentService).
        $user = $request->user();
        $cierre = CashClose::create([
            'fecha' => $start,
            'esperado' => $expected['por_metodo'],
            'esperado_total' => $expected['total'],
            'efectivo_esperado' => $efectivoEsperado,
            'efectivo_contado' => $contado,
            'diferencia' => round($contado - $efectivoEsperado, 2),
            'notas' => $validated['notas'] ?? null,
            // Sin "?->": dentro de ?? PHP ya suprime el acceso sobre null.
            'cerrado_por' => (string) ($user->id ?? ''),
            'cerrado_por_nombre' => $user?->name,
        ]);

        return response()->json([
            'message' => 'Corte de caja registrado.',
            'data' => $cierre,
        ], 201);
    }

    /**
     * Historial de cortes
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeCounterStaff();

        return response()->json([
            'data' => CashClose::orderBy('fecha', 'desc')->limit(60)->get(),
        ]);
    }
}
