<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Catálogo de Servicios</h2>
                <p class="ui-subtitle">Gestiona la oferta de servicios y precios de la barbería.</p>
            </div>
            <a href="{{ route('services.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo servicio
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="space-y-6">
                <!-- Filters -->
                <div class="ui-card-premium p-4">
                    <form method="GET" action="{{ route('services.index') }}" class="flex flex-wrap items-end gap-4">
                        <div class="w-full sm:w-64">
                            <label class="ui-label mb-1 block" for="categoria">Categoría</label>
                            <select id="categoria" name="categoria" class="ui-input">
                                <option value="">Todas las categorías</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}" @selected(($filters['categoria'] ?? '') === $category)>{{ $category }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="w-full sm:w-48">
                            <label class="ui-label mb-1 block" for="activo">Estado</label>
                            <select id="activo" name="activo" class="ui-input">
                                <option value="">Todos los estados</option>
                                <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                                <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="submit" class="ui-btn-secondary">
                                Filtrar
                            </button>
                            <a href="{{ route('services.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
                                Limpiar
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Desktop Table -->
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th>Categoría</th>
                                <th>Duración</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($services as $service)
                                <tr>
                                    <td class="font-medium">
                                        <div class="flex flex-col">
                                            <span>{{ $service->nombre }}</span>
                                            <span class="text-xs font-normal truncate max-w-xs">{{ $service->descripcion ?: 'Sin descripción' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="inline-flex items-center rounded-md bg-white/10 px-2 py-1 text-xs font-medium ring-1 ring-inset ring-white/10">
                                            {{ $service->categoria }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ $service->duracion_min }} min
                                        </div>
                                    </td>
                                    <td class="font-semibold">
                                        ${{ number_format((float) $service->precio, 2) }}
                                    </td>
                                    <td>
                                        @if($service->activo)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400 ring-1 ring-inset ring-green-400/20">
                                                Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-red-500/10 px-2 py-1 text-xs font-medium text-red-400 ring-1 ring-inset ring-red-400/20">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('services.edit', $service) }}" class="text-muted hover:text-blue-600 transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                            <form action="{{ route('services.destroy', $service) }}" method="POST" onsubmit="return confirm('¿Eliminar servicio?');">
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
                                        No hay servicios registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse ($services as $service)
                        <div class="ui-card p-4">
                            <div class="flex items-start justify-between mb-2">
                                <div>
                                    <h3 class="text-sm font-bold">{{ $service->nombre }}</h3>
                                    <p class="text-xs">{{ $service->categoria }}</p>
                                </div>
                                <span class="font-bold">${{ number_format((float) $service->precio, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-4 text-xs">
                                <div class="flex items-center gap-1">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                                    </svg>
                                    {{ $service->duracion_min }} min
                                </div>
                                @if($service->activo)
                                    <span class="text-green-500 font-medium">Activo</span>
                                @else
                                    <span class="text-red-500 font-medium">Inactivo</span>
                                @endif
                            </div>
                            <div class="mt-4 flex justify-end gap-3 border-t border-white/5 pt-3">
                                <a href="{{ route('services.edit', $service) }}" class="text-sm font-medium text-blue-600">Editar</a>
                                <form action="{{ route('services.destroy', $service) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">No hay servicios.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $services->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
