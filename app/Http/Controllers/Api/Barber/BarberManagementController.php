<?php

namespace App\Http\Controllers\Api\Barber;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Support\UploadedImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de gestión de barberos (solo administrador).
 * Expone listado paginado con búsqueda/filtro y edición de perfil de barbero.
 */
class BarberManagementController extends Controller
{
    // Lista barberos con búsqueda por nombre/email y filtro por estado activo, paginado
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('activo', '');

        $barbers = Barber::query()
            ->with('user:id,name,email')
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('activo', $status === '1');
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $barbers->getCollection()->map(fn (Barber $barber) => [
                'id' => $barber->id,
                // El binding de PUT barbers/manage/{barber} resuelve por
                // slug (Barber::getRouteKeyName(), via HasSlug), no por id
                // -- sin este campo el frontend no puede construir una URL
                // de edición que resuelva (ver guardrail #20 en
                // urbanblade-guardrails).
                'slug' => $barber->slug,
                'especialidades' => $barber->especialidades,
                'descripcion' => $barber->descripcion,
                'foto' => $barber->foto,
                'foto_url' => UploadedImage::url($barber->foto),
                'activo' => (bool) $barber->activo,
                // % de comisión sobre el precio de lista de cada servicio
                // que completa (ver BarberCommissionService), editable solo
                // aquí -- no tiene endpoint propio, vive en el mismo
                // formulario que el resto del perfil.
                'comision_pct' => (float) $barber->comision_pct,
                'user' => [
                    'id' => $barber->user?->id,
                    'name' => $barber->user?->name,
                    'email' => $barber->user?->email,
                ],
            ])->values(),
            'meta' => [
                'current_page' => $barbers->currentPage(),
                'last_page' => $barbers->lastPage(),
                'total' => $barbers->total(),
            ],
            'filters' => [
                'q' => $search,
                'activo' => $status,
            ],
        ]);
    }

    // Actualiza datos del usuario asociado (name/email) y del perfil de barbero en dos updates separados.
    // Con la foto como archivo, enviar POST multipart con _method=PUT.
    public function update(Request $request, Barber $barber): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$barber->user_id],
            'especialidades' => ['nullable', 'string', 'max:1000'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => UploadedImage::rules($request, 'foto'),
            'activo' => ['nullable', 'boolean'],
            'comision_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $barber->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $profile = [
            'especialidades' => $data['especialidades'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
            'comision_pct' => $data['comision_pct'] ?? 0,
        ];

        // La foto solo cambia si llega: un archivo nuevo o el campo "foto" (texto, o vacío para
        // quitarla). Un formulario que no la toca ya no la borra por omisión.
        if ($path = UploadedImage::store($request, 'foto', 'barbers', $barber->foto)) {
            $profile['foto'] = $path;
        } elseif ($request->exists('foto')) {
            $profile['foto'] = $data['foto'] ?? null;
        }

        $barber->update($profile);

        $barber->load('user:id,name,email');

        return response()->json([
            'message' => 'Barbero actualizado correctamente.',
            'data' => [
                'id' => $barber->id,
                'slug' => $barber->slug,
                'especialidades' => $barber->especialidades,
                'descripcion' => $barber->descripcion,
                'foto' => $barber->foto,
                'foto_url' => UploadedImage::url($barber->foto),
                'activo' => (bool) $barber->activo,
                'comision_pct' => (float) $barber->comision_pct,
                'user' => [
                    'id' => $barber->user?->id,
                    'name' => $barber->user?->name,
                    'email' => $barber->user?->email,
                ],
            ],
        ]);
    }

    // Aborta con 403 si no hay usuario autenticado o no tiene rol administrador
    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
