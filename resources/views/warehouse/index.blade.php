@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Almacén <span class="text-gold">Central</span></h2>
                <p class="ui-subtitle">Control de suministros y stock operativo.</p>
            </div>
            <a href="{{ route('warehouse.create') }}" class="ui-btn">
                <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Nuevo Suministro
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── ALERTA STOCK BAJO ────────────────────────── --}}
        @if($stats['bajo_stock'] > 0)
            <div class="flex items-center gap-4 rounded-2xl border border-amber-500/25 bg-amber-500/8 px-5 py-4">
                <div class="h-9 w-9 rounded-xl bg-amber-500/15 text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-black text-amber-300">{{ $stats['bajo_stock'] }} suministro{{ $stats['bajo_stock'] !== 1 ? 's' : '' }} con stock bajo</p>
                    <p class="text-[10px] text-amber-400/70 mt-0.5">Usa el filtro "Stock bajo" para verlos todos</p>
                </div>
                <a href="{{ route('warehouse.index', ['bajo_stock' => '1']) }}"
                   class="text-[10px] font-black uppercase tracking-widest text-amber-300 hover:text-white border border-amber-500/30 rounded-lg px-3 py-1.5 hover:bg-amber-500/10 transition-all shrink-0">
                    Ver ahora
                </a>
            </div>
        @endif

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-5">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Total</p>
                <p class="text-3xl font-black text-white">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-emerald-400/70 mb-2">Activos</p>
                <p class="text-3xl font-black text-emerald-400">{{ $stats['activos'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Inactivos</p>
                <p class="text-3xl font-black text-muted">{{ $stats['inactivos'] }}</p>
            </div>
            <div class="rounded-2xl border {{ $stats['bajo_stock'] > 0 ? 'border-amber-500/25 bg-amber-500/5' : 'border-white/8 bg-[#111]' }} p-5">
                <p class="text-[9px] font-black uppercase tracking-widest {{ $stats['bajo_stock'] > 0 ? 'text-amber-400/70' : 'text-muted' }} mb-2">Bajo Stock</p>
                <p class="text-3xl font-black {{ $stats['bajo_stock'] > 0 ? 'text-amber-400' : 'text-emerald-400' }}">{{ $stats['bajo_stock'] }}</p>
            </div>
            <div class="col-span-2 sm:col-span-1 rounded-2xl border border-gold/20 bg-gold/5 p-5">
                <p class="text-[9px] font-black uppercase tracking-widest text-gold/60 mb-2">Valor Total</p>
                <p class="text-2xl font-black text-gold">${{ number_format($stats['valor_total'], 0) }}</p>
            </div>
        </section>

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
                <form method="GET" action="{{ route('warehouse.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="sm:col-span-1">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Nombre del suministro...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Estado</label>
                            <select name="active" class="ui-input mt-1">
                                <option value="">Todos</option>
                                <option value="1" @selected(($filters['active'] ?? '') === '1')>Activos</option>
                                <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivos</option>
                            </select>
                        </div>
                        <div class="flex items-end pb-1">
                            <label class="flex items-center gap-3 cursor-pointer w-fit group">
                                <input type="checkbox" name="bajo_stock" value="1" @checked(!empty($filters['bajo_stock']))
                                       class="h-4 w-4 rounded border-white/20 bg-black/40 text-gold focus:ring-gold/30 focus:ring-offset-0">
                                <span class="text-[11px] font-bold uppercase tracking-widest text-muted group-hover:text-amber-400 transition-colors">
                                    Solo stock bajo
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('warehouse.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Active chips --}}
            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php $labels = ['q'=>'Búsqueda','active'=>'Estado','bajo_stock'=>'Stock bajo']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}{{ !in_array($key,['bajo_stock']) ? ': '.($key==='active'?($val==='1'?'Activo':'Inactivo'):$val) : '' }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $inventories->total() }} suministro{{ $inventories->total() !== 1 ? 's' : '' }}</p>
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Suministro</th>
                            <th>Existencias</th>
                            <th>Mínimo</th>
                            <th>Valor Unit.</th>
                            <th>Valor Total</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($inventories as $inventory)
                            @php $isLow = $inventory->quantity <= $inventory->min_stock; @endphp
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center overflow-hidden shrink-0">
                                            @if($inventory->imagen)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($inventory->imagen) }}" class="h-full w-full object-cover" loading="lazy">
                                            @else
                                                <svg class="h-5 w-5 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-bold text-white text-sm">{{ $inventory->name }}</p>
                                            @if($inventory->description)
                                                <p class="text-[10px] text-muted truncate max-w-[180px]">{{ $inventory->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col gap-1.5">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-sm {{ $isLow ? 'text-amber-400' : 'text-white' }}">{{ $inventory->quantity }}</span>
                                            @if($isLow)
                                                <span class="text-[9px] font-black border border-amber-500/25 bg-amber-500/10 text-amber-400 rounded-full px-1.5 py-0.5 uppercase">bajo</span>
                                            @endif
                                        </div>
                                        @php $pct = $inventory->min_stock > 0 ? min(100, ($inventory->quantity / ($inventory->min_stock * 2)) * 100) : 100; @endphp
                                        <div class="h-1 w-20 bg-white/5 rounded-full overflow-hidden">
                                            <div class="h-full {{ $isLow ? 'bg-amber-400' : 'bg-emerald-400' }} rounded-full" style="width:{{ $pct }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-muted text-sm">{{ $inventory->min_stock }}</td>
                                <td class="text-muted text-sm">${{ number_format($inventory->price, 2) }}</td>
                                <td class="font-black text-emerald-400">${{ number_format($inventory->quantity * $inventory->price, 2) }}</td>
                                <td>
                                    @if($inventory->active)
                                        <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-emerald-300">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                            Activo
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-muted">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white/20"></span>
                                            Inactivo
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('warehouse.show', $inventory) }}"
                                           class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-white hover:border-white/20 transition-all">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </a>
                                        @can('update', $inventory)
                                            <a href="{{ route('warehouse.edit', $inventory) }}"
                                               class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </a>
                                        @endcan
                                        @can('delete', $inventory)
                                            <form action="{{ route('warehouse.destroy', $inventory) }}" method="POST"
                                                  onsubmit="return confirm('¿Eliminar suministro permanentemente?');" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-20 text-center">
                                    <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Almacén vacío</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($inventories as $inventory)
                    @php $isLow = $inventory->quantity <= $inventory->min_stock; @endphp
                    <div class="rounded-2xl border {{ $isLow ? 'border-amber-500/20' : 'border-white/8' }} bg-[#111] p-4">
                        <div class="flex items-start gap-4 mb-3">
                            <div class="h-12 w-12 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center overflow-hidden shrink-0">
                                @if($inventory->imagen)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($inventory->imagen) }}" class="h-full w-full object-cover">
                                @else
                                    <svg class="h-6 w-6 text-white/10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                @endif
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-white text-sm">{{ $inventory->name }}</p>
                                <p class="text-[10px] text-muted mt-0.5">{{ $inventory->active ? 'Activo' : 'Inactivo' }}</p>
                            </div>
                            <span class="font-black text-emerald-400 text-base shrink-0">${{ number_format($inventory->price, 0) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-white/5 pt-3">
                            <div class="flex items-center gap-2 text-xs">
                                <span class="font-black {{ $isLow ? 'text-amber-400' : 'text-white' }}">{{ $inventory->quantity }}</span>
                                <span class="text-muted">/ {{ $inventory->min_stock }} mín</span>
                                @if($isLow)<span class="text-[9px] font-black text-amber-400 border border-amber-500/25 rounded-full px-1.5">bajo</span>@endif
                            </div>
                            <div class="flex gap-3">
                                @can('update', $inventory)
                                    <a href="{{ route('warehouse.edit', $inventory) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Editar</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center"><p class="text-sm text-muted">Sin suministros.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $inventories->links() }}</div>
        </section>
    </div>
</x-app-layout>
