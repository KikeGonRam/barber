@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
    $catColors = ['corte'=>'sky','barba'=>'amber','combo'=>'purple','tratamiento'=>'emerald'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Catálogo de <span class="text-gold">Servicios</span></h2>
                <p class="ui-subtitle">Gestiona los precios, duraciones y disponibilidad.</p>
            </div>
            <a href="{{ route('services.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nuevo Servicio
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-white/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-white uppercase tracking-widest">Filtros</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('services.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Nombre del servicio...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Categoría</label>
                            <select name="categoria" class="ui-input mt-1">
                                <option value="">Todas</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected(($filters['categoria'] ?? '') === $cat)>{{ ucfirst($cat) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Estado</label>
                            <select name="activo" class="ui-input mt-1">
                                <option value="">Todos</option>
                                <option value="1" @selected(($filters['activo'] ?? '') === '1')>Activos</option>
                                <option value="0" @selected(($filters['activo'] ?? '') === '0')>Inactivos</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('services.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php $labels = ['q'=>'Búsqueda','categoria'=>'Categoría','activo'=>'Estado']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}: {{ $key === 'activo' ? ($val === '1' ? 'Activo' : 'Inactivo') : $val }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $services->total() }} servicio{{ $services->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop --}}
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
                        @forelse($services as $service)
                            @php $catColor = $catColors[$service->categoria] ?? 'white'; @endphp
                            <tr class="group">
                                <td>
                                    <div>
                                        <p class="font-bold text-white text-sm">{{ $service->nombre }}</p>
                                        @if($service->descripcion)
                                            <p class="text-[10px] text-muted truncate max-w-xs">{{ $service->descripcion }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-wider
                                        border-{{ $catColor }}-500/25 bg-{{ $catColor }}-500/10 text-{{ $catColor }}-400">
                                        {{ $service->categoria }}
                                    </span>
                                </td>
                                <td>
                                    <span class="flex items-center gap-1.5 text-sm text-muted">
                                        <svg class="h-3.5 w-3.5 text-gold/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $service->duracion_min }} min
                                    </span>
                                </td>
                                <td>
                                    <span class="font-black text-white text-base">${{ number_format((float)$service->precio, 2) }}</span>
                                </td>
                                <td>
                                    @if($service->activo)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full border border-red-500/25 bg-red-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-red-400">
                                            <span class="h-1.5 w-1.5 rounded-full bg-red-400"></span> Inactivo
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('services.edit', $service) }}"
                                           class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('services.destroy', $service) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar servicio?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin servicios registrados</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($services as $service)
                    @php $catColor = $catColors[$service->categoria] ?? 'white'; @endphp
                    <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-black text-white text-sm">{{ $service->nombre }}</p>
                                <span class="inline-flex items-center rounded-full border border-{{ $catColor }}-500/25 bg-{{ $catColor }}-500/10 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-{{ $catColor }}-400 mt-1">
                                    {{ $service->categoria }}
                                </span>
                            </div>
                            <span class="font-black text-white text-lg">${{ number_format((float)$service->precio, 0) }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-xs border-t border-white/5 pt-3">
                            <span class="text-muted flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $service->duracion_min }} min
                            </span>
                            <span class="{{ $service->activo ? 'text-emerald-400' : 'text-red-400' }} font-bold uppercase text-[10px]">
                                {{ $service->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>
                        <div class="mt-3 flex justify-end gap-3 border-t border-white/5 pt-3">
                            <a href="{{ route('services.edit', $service) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Editar</a>
                            <form action="{{ route('services.destroy', $service) }}" method="POST" onsubmit="return confirm('¿Eliminar?');">
                                @csrf @method('DELETE')
                                <button class="text-[10px] font-black uppercase tracking-widest text-red-500">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center"><p class="text-sm text-muted">Sin servicios.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $services->links() }}</div>
        </section>
    </div>
</x-app-layout>
