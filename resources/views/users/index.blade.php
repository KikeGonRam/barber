<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestion de Usuarios</h2>
                <p class="ui-subtitle">Administra los accesos y roles del sistema.</p>
            </div>
            <a href="{{ route('users.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo usuario
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="space-y-6">
                <!-- Filters -->
                <div class="ui-card-premium p-4">
                    <form method="GET" action="{{ route('users.index') }}" class="flex flex-col gap-4 lg:flex-row lg:items-end">
                        <div class="w-full lg:w-1/3">
                            <label class="ui-label mb-1 block" for="q">Buscar</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="q" id="q" value="{{ request('q') }}" 
                                    class="ui-input pl-10" placeholder="Nombre, email...">
                            </div>
                        </div>
                        <div class="w-full lg:w-1/4">
                            <label class="ui-label mb-1 block" for="role">Rol</label>
                            <select name="role" id="role" class="ui-input">
                                <option value="">Todos los roles</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(request('role') == $role)>
                                        {{ ucfirst($role) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="ui-btn-secondary">
                                Filtrar
                            </button>
                            @if(request('q') || request('role'))
                                <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Roles</th>
                                <th>Estado</th>
                                <th>Registrado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="font-medium">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-white/10 flex items-center justify-center text-xs font-bold">
                                                {{ substr($user->name, 0, 2) }}
                                            </div>
                                            {{ $user->name }}
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @foreach($user->roles as $role)
                                            <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2 py-1 text-xs font-medium text-blue-400 ring-1 ring-inset ring-blue-400/20">
                                                {{ $role->name }}
                                            </span>
                                        @endforeach
                                    </td>
                                    <td>
                                        @if($user->email_verified_at)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400 ring-1 ring-inset ring-green-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Verificado
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-yellow-500/10 px-2 py-1 text-xs font-medium text-yellow-400 ring-1 ring-inset ring-yellow-500/20">
                                                Pendiente
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-xs">
                                        {{ $user->created_at->format('d M, Y') }}
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('users.edit', $user) }}" class="text-muted hover:text-blue-600 transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('¿Estas seguro de eliminar este usuario?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-muted hover:text-red-600 transition-colors" title="Eliminar">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="h-10 w-10 text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            <p>No se encontraron usuarios con los filtros seleccionados.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse($users as $user)
                        <div class="ui-card p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">
                                        {{ substr($user->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium">{{ $user->name }}</h3>
                                        <p class="text-xs">{{ $user->email }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center">
                                     @foreach($user->roles as $role)
                                        <span class="inline-flex items-center rounded-full bg-blue-500/10 px-2 py-0.5 text-xs font-medium text-blue-400 ring-1 ring-inset ring-blue-400/20">
                                            {{ $role->name }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-4 text-xs border-t border-white/5 pt-3">
                                <div>
                                    <span class="block text-muted mb-1">Estado</span>
                                    @if($user->email_verified_at)
                                        <span class="text-green-500 font-medium">Verificado</span>
                                    @else
                                        <span class="text-yellow-500 font-medium">Pendiente</span>
                                    @endif
                                </div>
                                <div>
                                    <span class="block text-muted mb-1">Registrado</span>
                                    {{ $user->created_at->format('d/m/Y') }}
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-3 pt-2">
                                <a href="{{ route('users.edit', $user) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">Editar</a>
                                <form action="{{ route('users.destroy', $user) }}" method="POST" onsubmit="return confirm('¿Eliminar usuario?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:text-red-800">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">
                            No hay usuarios.
                        </div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $users->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
