<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $roleFilter = (string) $request->query('role', '');

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

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return view('users.index', compact('users', 'roles', 'search', 'roleFilter'));
    }

    public function create(): View
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name');

        return view('users.create', compact('roles'));
    }

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
