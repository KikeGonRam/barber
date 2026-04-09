<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BarberManagementController extends Controller
{
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
                'especialidades' => $barber->especialidades,
                'descripcion' => $barber->descripcion,
                'foto' => $barber->foto,
                'activo' => (bool) $barber->activo,
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

    public function update(Request $request, Barber $barber): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$barber->user_id],
            'especialidades' => ['nullable', 'string', 'max:1000'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'foto' => ['nullable', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $barber->user()->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        $barber->update([
            'especialidades' => $data['especialidades'] ?? null,
            'descripcion' => $data['descripcion'] ?? null,
            'foto' => $data['foto'] ?? null,
            'activo' => (bool) ($data['activo'] ?? false),
        ]);

        $barber->load('user:id,name,email');

        return response()->json([
            'message' => 'Barbero actualizado correctamente.',
            'data' => [
                'id' => $barber->id,
                'especialidades' => $barber->especialidades,
                'descripcion' => $barber->descripcion,
                'foto' => $barber->foto,
                'activo' => (bool) $barber->activo,
                'user' => [
                    'id' => $barber->user?->id,
                    'name' => $barber->user?->name,
                    'email' => $barber->user?->email,
                ],
            ],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'No autorizado.');
    }
}
