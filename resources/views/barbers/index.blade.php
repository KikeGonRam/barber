<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Equipo de Barberos</h2>
                <p class="ui-subtitle">Gestiona el personal, especialidades y disponibilidad.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="ui-badge">
                    {{ $barbers->total() }} Barberos registrados
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <section class="space-y-6">
                <!-- Filters -->
                <div class="ui-card-premium p-4">
                    <form method="GET" action="{{ route('barbers.index') }}" class="flex flex-wrap items-end gap-4">
                        <div class="w-full sm:w-64">
                            <label class="ui-label mb-1 block" for="q">Buscar</label>
                            <input id="q" name="q" value="{{ $search }}" class="ui-input" placeholder="Nombre o email">
                        </div>
                        <div class="w-full sm:w-48">
                            <label class="ui-label mb-1 block" for="activo">Estado</label>
                            <select id="activo" name="activo" class="ui-input">
                                <option value="">Todos los estados</option>
                                <option value="1" @selected($status === '1')>Activos</option>
                                <option value="0" @selected($status === '0')>Inactivos</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit" class="ui-btn-secondary">
                                Filtrar
                            </button>
                            <a href="{{ route('barbers.index') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">
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
                                <th>Barbero</th>
                                <th>Especialidades</th>
                                <th>Email</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($barbers as $barber)
                                <tr>
                                    <td class="font-medium">
                                        <div class="flex items-center gap-3">
                                            <div class="h-8 w-8 rounded-full bg-white/10 flex items-center justify-center text-xs font-bold">
                                                {{ substr($barber->user?->name ?? 'BB', 0, 2) }}
                                            </div>
                                            {{ $barber->user?->name ?? 'Sin usuario' }}
                                        </div>
                                    </td>
                                    <td class="text-xs italic">
                                        {{ $barber->especialidades ?: 'General' }}
                                    </td>
                                    <td>
                                        {{ $barber->user?->email ?: '-' }}
                                    </td>
                                    <td>
                                        @if($barber->activo)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-500/10 px-2 py-1 text-xs font-medium text-green-400 ring-1 ring-inset ring-green-400/20">
                                                <span class="h-1 w-1 rounded-full bg-green-500"></span> Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-white/5 px-2 py-1 text-xs font-medium text-muted ring-1 ring-inset ring-white/10">
                                                Inactivo
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('barbers.edit', $barber) }}" class="text-muted hover:text-blue-600 transition-colors" title="Editar">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center">
                                        No se encontraron barberos con los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="space-y-4 md:hidden">
                    @forelse($barbers as $barber)
                        <div class="ui-card p-4">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 rounded-full bg-white/10 flex items-center justify-center text-sm font-bold">
                                        {{ substr($barber->user?->name ?? 'BB', 0, 2) }}
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold">{{ $barber->user?->name ?? 'Sin usuario' }}</h3>
                                        <p class="text-[10px] text-muted uppercase tracking-wider">{{ $barber->user?->email ?: '-' }}</p>
                                    </div>
                                </div>
                                @if($barber->activo)
                                    <span class="text-[10px] font-bold text-green-500 uppercase">Activo</span>
                                @else
                                    <span class="text-[10px] font-bold text-muted uppercase">Inactivo</span>
                                @endif
                            </div>
                            <div class="text-xs italic mb-4">
                                <span class="text-muted not-italic mr-1">Especialidades:</span>
                                {{ $barber->especialidades ?: 'General' }}
                            </div>
                            <div class="flex justify-end pt-3 border-t border-white/5">
                                <a href="{{ route('barbers.edit', $barber) }}" class="text-sm font-medium text-blue-600">Gestionar Perfil</a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8">No hay barberos.</div>
                    @endforelse
                </div>

                <div class="mt-4">
                    {{ $barbers->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
