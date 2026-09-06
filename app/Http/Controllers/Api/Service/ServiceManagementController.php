<?php

namespace App\Http\Controllers\Api\Service;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Services\Service\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * API de administración del catálogo de servicios (crear/editar/eliminar/listar),
 * exclusiva para administradores.
 */
class ServiceManagementController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService) {}

    /**
     * Lista paginada de servicios con filtros por categoría/activo, más el catálogo de categorías.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $filters = $request->only(['q', 'categoria', 'activo']);
        $services = $this->serviceService->list($filters, 20);

        return response()->json([
            'data' => collect($services->items())->map(fn (Service $service) => $this->servicePayload($service))->values(),
            'meta' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'total' => $services->total(),
            ],
            'categories' => $this->serviceService->categories(),
        ]);
    }

    /**
     * Crea un nuevo servicio en el catálogo.
     */
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
            'data' => $this->servicePayload($created),
        ], 201);
    }

    /**
     * Actualiza los datos de un servicio existente.
     */
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
            'data' => $this->servicePayload($service),
        ]);
    }

    /**
     * Elimina un servicio del catálogo.
     */
    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorizeAdmin($request);

        $this->serviceService->delete($service);

        return response()->json([
            'message' => 'Servicio eliminado correctamente.',
        ]);
    }

    /**
     * Restringe el acceso solo a administradores; el resto recibe 403.
     */
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }

    // Serializa un servicio a array de respuesta; imagen_url se calcula aquí para no repetirlo en cada endpoint.
    private function servicePayload(Service $service): array
    {
        return [
            'id' => $service->id,
            // Service usa HasSlug -> getRouteKeyName() = 'slug': las URLs
            // PUT/DELETE de este mismo controlador ligan por slug, no por
            // id (ver guardrail #20 de este repo) — se manda aquí para que
            // el frontend no tenga que adivinarlo ni caer en el mismo 404
            // fantasma que ya pasó con Client/Barber.
            'slug' => $service->slug,
            'nombre' => $service->nombre,
            'categoria' => $service->categoria,
            'precio' => $service->precio,
            'duracion_min' => $service->duracion_min,
            'imagen' => $service->imagen,
            'imagen_url' => $service->imagen ? Storage::disk('public')->url($service->imagen) : null,
            'descripcion' => $service->descripcion,
            'activo' => (bool) $service->activo,
        ];
    }
}
