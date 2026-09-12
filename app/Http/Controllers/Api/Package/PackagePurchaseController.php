<?php

namespace App\Http\Controllers\Api\Package;

use App\Exceptions\Domain\PackageException;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\ServicePackage;
use App\Services\Package\PackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @group Paquetes prepagados
 *
 * Catálogo de paquetes disponibles, compra (efectivo por staff o tarjeta
 * vía Stripe) y consulta de los paquetes de un cliente.
 */
class PackagePurchaseController extends Controller
{
    public function __construct(
        private readonly PackageService $packages,
    ) {}

    /**
     * Catálogo de paquetes activos disponibles para comprar.
     */
    public function catalog(): JsonResponse
    {
        $packages = ServicePackage::where('activo', true)->with('service')->get();

        return response()->json([
            'data' => $packages->map(fn (ServicePackage $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'service' => ['id' => $p->service?->id, 'nombre' => $p->service?->nombre],
                'cantidad_usos' => $p->cantidad_usos,
                'precio' => $p->precio,
                'vigencia_dias' => $p->vigencia_dias,
            ])->values(),
        ]);
    }

    /**
     * Mis paquetes (cliente) o los de un cliente indicado (staff).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);
        $isClient = $user?->hasRole('cliente') && $user->clientProfile;

        abort_unless($isStaff || $isClient, 403, 'No autorizado.');

        $clientId = $isClient && ! $isStaff
            ? (string) $user->clientProfile->id
            : $request->query('client_id');

        abort_if($isStaff && ! $clientId, 422, 'Falta indicar client_id.');

        $packages = ClientPackage::where('client_id', $clientId)
            ->with(['servicePackage', 'service'])
            ->latest('comprado_en')
            ->get();

        return response()->json([
            'data' => $packages->map(fn (ClientPackage $p) => $this->payload($p))->values(),
        ]);
    }

    /**
     * Compra en efectivo, registrada por staff (Admin/Recepcionista).
     *
     * @bodyParam client_id string required ID del cliente. Example: 66f0...
     * @bodyParam service_package_id string required ID del paquete. Example: 66f1...
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->hasAnyRole(['administrador', 'recepcionista']), 403, 'No autorizado.');

        $validated = $request->validate([
            'client_id' => ['required', 'string', 'exists:clients,id'],
            'service_package_id' => ['required', 'string', 'exists:service_packages,id'],
        ]);

        $client = Client::findOrFail($validated['client_id']);
        $package = ServicePackage::findOrFail($validated['service_package_id']);

        try {
            $clientPackage = $this->packages->purchaseCash($client, $package, (string) $user->id);
        } catch (PackageException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Paquete vendido correctamente.',
            'data' => $this->payload($clientPackage->fresh(['servicePackage', 'service'])),
        ], 201);
    }

    /**
     * Crea el PaymentIntent de Stripe para comprar un paquete con tarjeta.
     *
     * @bodyParam service_package_id string required ID del paquete. Example: 66f1...
     */
    public function stripeIntent(Request $request): JsonResponse
    {
        $user = $request->user();
        $isStaff = $user?->hasAnyRole(['administrador', 'recepcionista']);
        $isClient = $user?->hasRole('cliente') && $user->clientProfile;

        abort_unless($isStaff || $isClient, 403, 'No autorizado.');

        $validated = $request->validate([
            'service_package_id' => ['required', 'string', 'exists:service_packages,id'],
            'client_id' => [$isStaff ? 'required' : 'nullable', 'string', 'exists:clients,id'],
        ]);

        $client = $isStaff ? Client::findOrFail($validated['client_id']) : $user->clientProfile;
        $package = ServicePackage::findOrFail($validated['service_package_id']);

        try {
            $data = $this->packages->createStripeIntent($client, $package);

            return response()->json(['data' => $data]);
        } catch (PackageException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::warning('No se pudo crear intento de pago Stripe para paquete.', [
                'service_package_id' => $validated['service_package_id'],
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo crear el intento de pago Stripe. Intenta de nuevo o usa efectivo.',
            ], 422);
        }
    }

    private function payload(ClientPackage $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->servicePackage?->nombre,
            'service' => ['id' => $p->service?->id, 'nombre' => $p->service?->nombre],
            'usos_totales' => $p->usos_totales,
            'usos_restantes' => $p->usos_restantes,
            'precio_pagado' => $p->precio_pagado,
            'metodo_pago' => $p->metodo_pago,
            'comprado_en' => optional($p->comprado_en)->toIso8601String(),
            'expira_en' => optional($p->expira_en)->toIso8601String(),
            'estado' => $p->estado,
        ];
    }
}
