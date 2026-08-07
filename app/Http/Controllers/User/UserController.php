<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Controlador de gestión de usuarios (panel de administración): CRUD de
 * cuentas y asignación de roles, con creación/sincronización automática
 * de los perfiles de dominio asociados (Barber/Client).
 */
class UserController extends Controller
{
    /**
     * Listado paginado de usuarios con búsqueda por nombre/email y filtros
     * de rol y estado de verificación de email.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $roleFilter = (string) $request->query('role', '');
        $verified = $request->query('verified', '');

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($s) => $s
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($roleFilter !== '', fn ($q) => $q->whereRoleName($roleFilter))
            ->when($verified === '1', fn ($q) => $q->whereNotNull('email_verified_at'))
            ->when($verified === '0', fn ($q) => $q->whereNull('email_verified_at'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name');

        return view('users.index', compact('users', 'roles', 'search', 'roleFilter'));
    }

    public function create(): View
    {
        // Solo permitir crear barbero y recepcionista si el usuario actual es admin
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', ['barbero', 'recepcionista', 'cliente'])
            ->orderBy('name')
            ->pluck('name');

        return view('users.create', compact('roles'));
    }

    /**
     * Crea el usuario y su perfil de dominio correspondiente al rol elegido
     * (Barber/Client) dentro de una transacción para que ambas escrituras
     * sean atómicas. email_verified_at se marca de inmediato porque el
     * usuario es dado de alta manualmente por un administrador.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data): void {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $user->syncRoles([$data['role']]);
            $this->syncRoleProfiles($user, $data['role']);
        });

        return redirect()->route('users.index')->with('status', 'Usuario creado correctamente.');
    }

    public function edit(User $user): View
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Actualiza el usuario y re-sincroniza rol y perfil de dominio.
     * La contraseña es opcional: solo se rehashea si se envió una nueva.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $user): void {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            $user->syncRoles([$data['role']]);
            $this->syncRoleProfiles($user, $data['role']);
        });

        return redirect()->route('users.index')->with('status', 'Usuario actualizado correctamente.');
    }

    /**
     * Elimina un usuario, impidiendo que un administrador se autoelimine
     * (evitaría quedarse sin sesión ni forma de revertirlo).
     */
    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->withErrors([
                'general' => 'No puedes eliminar tu propio usuario.',
            ]);
        }

        $user->delete();

        return redirect()->route('users.index')->with('status', 'Usuario eliminado correctamente.');
    }

    /**
     * Crea el perfil de dominio (Barber o Client) asociado al rol si aún
     * no existe. firstOrCreate evita duplicar el perfil al reasignar el
     * mismo rol en una actualización.
     */
    private function syncRoleProfiles(User $user, string $role): void
    {
        if ($role === 'barbero') {
            Barber::query()->firstOrCreate(['user_id' => $user->id], ['activo' => true]);
        }

        if ($role === 'cliente') {
            Client::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['preferencias_notificacion' => [
                    'in_app' => true,
                    'email' => true,
                    'sms' => false,
                    'whatsapp' => false,
                ]]
            );
        }
    }
}
