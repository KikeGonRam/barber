@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null && $v !== '0');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Gestión de <span class="text-gold">Clientes</span></h2>
                <p class="ui-subtitle">Base de datos completa de clientes registrados.</p>
            </div>
            <a href="{{ route('clients.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                Nuevo Cliente
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach([
                ['label'=>'Total Clientes', 'val'=>$stats['total'],     'color'=>'white',   'sub'=>'Registrados'],
                ['label'=>'Con Citas',       'val'=>$stats['con_citas'], 'color'=>'emerald', 'sub'=>'Al menos una cita'],
                ['label'=>'Sin Citas',       'val'=>$stats['sin_citas'], 'color'=>'amber',   'sub'=>'Sin historial'],
                ['label'=>'Este Mes',        'val'=>$stats['este_mes'],  'color'=>'blue',    'sub'=>'Nuevos en ' . now()->format('M')],
            ] as $stat)
                <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                    <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">{{ $stat['label'] }}</p>
                    <p class="text-2xl font-black {{ $stat['color'] === 'white' ? 'text-white' : 'text-'.$stat['color'].'-400' }}">{{ $stat['val'] }}</p>
                    <p class="text-[10px] text-muted/60 mt-0.5">{{ $stat['sub'] }}</p>
                </div>
            @endforeach
        </section>

        {{-- ── FILTROS ─────────────────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-white/6" @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-white uppercase tracking-widest">Filtros Avanzados</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('clients.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Nombre o email...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Registro desde</label>
                            <input type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}" class="ui-input mt-1">
                        </div>
                        <div>
                            <label class="ui-label">Registro hasta</label>
                            <input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}" class="ui-input mt-1">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="flex items-center gap-3 cursor-pointer w-fit group">
                            <div class="relative flex items-center">
                                <input type="checkbox" name="sin_citas" value="1" @checked(!empty($filters['sin_citas']))
                                       class="h-4 w-4 rounded border-white/20 bg-black/40 text-gold focus:ring-gold/30 focus:ring-offset-0 transition-all">
                            </div>
                            <span class="text-[11px] font-bold uppercase tracking-widest text-muted group-hover:text-white transition-colors">
                                Mostrar solo clientes sin citas
                            </span>
                        </label>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('clients.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
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
                        @php $labels = ['q'=>'Búsqueda','fecha_desde'=>'Desde','fecha_hasta'=>'Hasta','sin_citas'=>'Sin citas']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}@if($key !== 'sin_citas'): {{ $val }}@endif
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $clients->total() }} cliente{{ $clients->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th class="text-center">Citas</th>
                            <th>Nacimiento</th>
                            <th>Registrado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-white/10 to-white/5 border border-white/10 flex items-center justify-center text-[11px] font-black text-white shrink-0">
                                            {{ strtoupper(substr($client->user?->name ?? 'CL', 0, 2)) }}
                                        </div>
                                        <span class="font-bold text-white text-sm">{{ $client->user?->name ?? 'Sin usuario' }}</span>
                                    </div>
                                </td>
                                <td class="text-muted text-sm">{{ $client->user?->email ?: '—' }}</td>
                                <td class="text-muted text-sm">{{ $client->telefono ?: '—' }}</td>
                                <td class="text-center">
                                    <span class="inline-flex items-center justify-center min-w-[2rem] rounded-full border {{ ($client->appointments_count ?? 0) > 0 ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-white/10 bg-white/5 text-muted' }} px-2 py-0.5 text-[11px] font-black">
                                        {{ $client->appointments_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-muted text-sm">{{ $client->fecha_nacimiento?->format('d M, Y') ?: '—' }}</td>
                                <td class="text-muted text-xs">{{ $client->created_at?->format('d/m/Y') }}</td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('clients.show', $client) }}"
                                           class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 flex items-center text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all" title="Ver Perfil">
                                            Perfil
                                        </a>
                                        <a href="{{ route('clients.edit', $client) }}"
                                           class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('clients.destroy', $client) }}" method="POST"
                                              onsubmit="return confirm('¿Eliminar cliente {{ addslashes($client->user?->name ?? '') }}?');" class="inline">
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
                                <td colspan="7" class="px-6 py-16 text-center">
                                    <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin clientes que mostrar</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($clients as $client)
                    <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-white/8 border border-white/10 flex items-center justify-center text-sm font-black text-white">
                                    {{ strtoupper(substr($client->user?->name ?? 'CL', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $client->user?->name ?? 'Sin usuario' }}</p>
                                    <p class="text-[10px] text-muted">{{ $client->user?->email ?: '—' }}</p>
                                </div>
                            </div>
                            <span class="text-[9px] font-black border rounded-full px-2 py-0.5 {{ ($client->appointments_count ?? 0) > 0 ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-white/10 bg-white/5 text-muted' }}">
                                {{ $client->appointments_count ?? 0 }} citas
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs border-t border-white/5 pt-3">
                            <div><p class="text-muted text-[9px] uppercase font-bold mb-0.5">Teléfono</p><p class="text-white">{{ $client->telefono ?: '—' }}</p></div>
                            <div><p class="text-muted text-[9px] uppercase font-bold mb-0.5">Registrado</p><p class="text-white">{{ $client->created_at?->format('d/m/Y') }}</p></div>
                        </div>
                        <div class="mt-3 flex justify-end gap-3 border-t border-white/5 pt-3">
                            <a href="{{ route('clients.edit', $client) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Editar</a>
                            <form action="{{ route('clients.destroy', $client) }}" method="POST" onsubmit="return confirm('¿Eliminar cliente?');">
                                @csrf @method('DELETE')
                                <button class="text-[10px] font-black uppercase tracking-widest text-red-500">Eliminar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-12 text-center">
                        <p class="text-sm text-muted">Sin clientes.</p>
                    </div>
                @endforelse
            </div>

            <div class="mt-6">{{ $clients->links() }}</div>
        </section>
    </div>
</x-app-layout>
