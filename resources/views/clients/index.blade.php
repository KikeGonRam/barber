<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de Clientes</h2>
                <p class="ui-subtitle">Administra la base de datos de clientes y su información de contacto.</p>
            </div>
            <a href="{{ route('clients.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo cliente
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="space-y-6">
                <!-- Filters -->
                <div class="ui-card-premium p-4">
                    <form method="GET" action="{{ route('clients.index') }}" class="flex flex-wrap items-end gap-4">
                        <div class="w-full sm:w-80">
                            <label class="ui-label mb-1 block" for="q">Buscar</label>
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="q" id="q" value="{{ $search }}" 
                                    class="ui-input pl-10" placeholder="Nombre o email...">
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="ui-btn-secondary">
                                Filtrar
                            </button>
                            @if($search)
                                <a href="{{ route('clients.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </form>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table table-fixed min-w-[1100px]">
                        <thead>
                            <tr>
                                <th class="w-[28%]">Cliente</th>
                                <th class="w-[25%]">Email</th>
                                <th class="w-[18%]">Teléfono</th>
                                <th class="w-[13%]">Nacimiento</th>
                                <th class="w-[10%]">Registrado</th>
                                <th class="w-[6%] text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                                <tr>
                                    <td class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-white/10 flex items-center justify-center text-xs font-bold">
                                                {{ substr($client->user?->name ?? 'CL', 0, 2) }}
                                            </div>
                                            <span class="truncate">{{ $client->user?->name ?? 'Sin usuario' }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap overflow-hidden text-ellipsis">{{ $client->user?->email ?: '-' }}</td>
                                    <td class="whitespace-nowrap">
                                        <div class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            <span class="truncate">{{ $client->telefono ?: 'No registrado' }}</span>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        {{ $client->fecha_nacimiento?->format('d M, Y') ?: '-' }}
                                    </td>
                                    <td class="text-muted text-xs whitespace-nowrap">
                                        {{ $client->created_at?->format('d/m/Y') }}
                                    </td>
                                    <td class="text-right whitespace-nowrap">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('clients.edit', $client) }}" class="text-muted hover:text-blue-600 transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('clients.destroy', $client) }}" method="POST" onsubmit="return confirm('¿Eliminar cliente?');">
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
                                        No se encontraron clientes con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse($clients as $client)
                        <div class="ui-card p-4">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">
                                        {{ substr($client->user?->name ?? 'CL', 0, 2) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold">{{ $client->user?->name ?? 'Sin usuario' }}</h3>
                                        <p class="text-xs">{{ $client->user?->email ?: '-' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-4 text-xs border-t border-white/5 pt-3">
                                <div>
                                    <span class="block text-muted mb-1">Teléfono</span>
                                    {{ $client->telefono ?: '-' }}
                                </div>
                                <div>
                                    <span class="block text-muted mb-1">Nacimiento</span>
                                    {{ $client->fecha_nacimiento?->format('d/m/Y') ?: '-' }}
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-3 pt-2">
                                <a href="{{ route('clients.edit', $client) }}" class="text-sm font-medium text-blue-600">Editar</a>
                                <form action="{{ route('clients.destroy', $client) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-500">No hay clientes.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $clients->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
