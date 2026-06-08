<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Service\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceManagementController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $filters = $request->only(['categoria', 'activo']);
        $services = $this->serviceService->list($filters, 50);

        return response()->json([
            'data' => collect($services->items())->map(fn (Service $service) => [
                'id' => $service->id,
                'nombre' => $service->nombre,
                'categoria' => $service->categoria,
                'precio' => $service->precio,
                'duracion_min' => $service->duracion_min,
                'imagen' => $service->imagen,
                'descripcion' => $service->descripcion,
                'activo' => (bool) $service->activo,
            ])->values(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'total' => $services->total(),
            ],
            'categories' => $this->serviceService->categories(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_min' => ['required', 'integer', 'min:5', 'max:600'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $created = $this->serviceService->create($data);

        return response()->json([
            'message' => 'Servicio creado correctamente.',
            'data' => [
                'id' => $created->id,
                'nombre' => $created->nombre,
                'categoria' => $created->categoria,
                'precio' => $created->precio,
                'duracion_min' => $created->duracion_min,
                'imagen' => $created->imagen,
                'descripcion' => $created->descripcion,
                'activo' => (bool) $created->activo,
            ],
        ], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'categoria' => ['required', 'string', 'max:100'],
            'precio' => ['required', 'numeric', 'min:0'],
            'duracion_min' => ['required', 'integer', 'min:5', 'max:600'],
            'imagen' => ['nullable', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $this->serviceService->update($service, $data);
        $service->refresh();

        return response()->json([
            'message' => 'Servicio actualizado correctamente.',
            'data' => [
                'id' => $service->id,
                'nombre' => $service->nombre,
                'categoria' => $service->categoria,
                'precio' => $service->precio,
                'duracion_min' => $service->duracion_min,
                'imagen' => $service->imagen,
                'descripcion' => $service->descripcion,
                'activo' => (bool) $service->activo,
            ],
        ]);
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorizeAdmin($request);

        $this->serviceService->delete($service);

        return response()->json([
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
