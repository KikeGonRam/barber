<?php

namespace App\Http\Controllers\Api\Admin\Package;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de administración de plantillas de paquetes prepagados
 * (crear/editar/desactivar/listar), exclusiva para administradores.
 */
class ServicePackageController extends Controller
{
    /**
     * Lista todas las plantillas de paquetes.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $packages = ServicePackage::with('service')->latest()->get();

        return response()->json([
            'data' => $packages->map(fn (ServicePackage $p) => $this->payload($p))->values(),
        ]);
    }

    /**
     * Crea una nueva plantilla de paquete.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'cantidad_usos' => ['required', 'integer', 'min:2', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0.01'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1', 'max:730'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $package = ServicePackage::create($data + ['activo' => $data['activo'] ?? true]);

        return response()->json([
            'message' => 'Paquete creado correctamente.',
            'data' => $this->payload($package->fresh('service')),
        ], 201);
    }

    /**
     * Actualiza una plantilla de paquete existente. No afecta a los
     * ClientPackage ya comprados (su precio quedó congelado al comprarlos).
     */
    public function update(Request $request, ServicePackage $servicePackage): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'service_id' => ['required', 'string', 'exists:services,id'],
            'cantidad_usos' => ['required', 'integer', 'min:2', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0.01'],
            'vigencia_dias' => ['nullable', 'integer', 'min:1', 'max:730'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $servicePackage->update($data);

        return response()->json([
            'message' => 'Paquete actualizado correctamente.',
            'data' => $this->payload($servicePackage->fresh('service')),
        ]);
    }

    /**
     * Desactiva un paquete (deja de venderse) en vez de eliminarlo -- los
     * ClientPackage ya vendidos deben seguir siendo canjeables.
     */
    public function destroy(Request $request, ServicePackage $servicePackage): JsonResponse
    {
        $this->authorizeAdmin($request);

        $servicePackage->update(['activo' => false]);

        return response()->json([
            'message' => 'Paquete desactivado. Los ya vendidos siguen siendo válidos.',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }

    private function payload(ServicePackage $package): array
    {
        return [
            'id' => $package->id,
            'nombre' => $package->nombre,
            'service' => [
                'id' => $package->service?->id,
                'nombre' => $package->service?->nombre,
            ],
            'cantidad_usos' => $package->cantidad_usos,
            'precio' => $package->precio,
            'vigencia_dias' => $package->vigencia_dias,
            'activo' => (bool) $package->activo,
        ];
    }
}
