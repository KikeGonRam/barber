<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if(! $user || ! $user->hasRole('administrador'), 403, 'Solo administradores pueden consultar usuarios.');

        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));

        $users = User::query()
            ->with('roles:id,name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter): void {
                $query->role($roleFilter);
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return response()->json([
            'data' => $users->getCollection()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => optional($user->email_verified_at)?->toAtomString(),
                'created_at' => optional($user->created_at)?->toAtomString(),
                'roles' => $user->roles->pluck('name')->values(),
            ])->values(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => [
                'q' => $search,
                'role' => $roleFilter,
            ],
            'roles' => Role::query()
                ->where('guard_name', 'web')
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }
}