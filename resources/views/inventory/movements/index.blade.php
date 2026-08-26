@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Control de <span class="text-gold">Movimientos</span></h2>
                <p class="ui-subtitle">Historial de entradas y salidas de inventario.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('inventory.products.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    Productos
                </a>
                <a href="{{ route('inventory.movements.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Registrar Movimiento
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">Total</p>
                <p class="text-2xl font-black text-white">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400/70 mb-1">Entradas</p>
                <p class="text-2xl font-black text-emerald-400">{{ $stats['entradas'] }}</p>
            </div>
            <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-red-400/70 mb-1">Salidas</p>
                <p class="text-2xl font-black text-red-400">{{ $stats['salidas'] }}</p>
            </div>
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-blue-400/70 mb-1">Hoy</p>
                <p class="text-2xl font-black text-blue-400">{{ $stats['hoy'] }}</p>
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
                <form method="GET" action="{{ route('inventory.movements.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Producto o motivo...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Tipo</label>
                            <select name="tipo" class="ui-input mt-1">
                                <option value="">Todos</option>
                                <option value="entrada" @selected(($filters['tipo'] ?? '') === 'entrada')>Entrada</option>
                                <option value="salida"  @selected(($filters['tipo'] ?? '') === 'salida')>Salida</option>
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Producto</label>
                            <select name="product_id" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}" @selected(($filters['product_id'] ?? '') == $prod->id)>{{ $prod->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Fecha desde</label>
                            <input type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}" class="ui-input mt-1">
                        </div>
                        <div>
                            <label class="ui-label">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}" class="ui-input mt-1">
                        </div>
                    </div>
                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">Aplicar Filtros</button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('inventory.movements.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $movements->total() }} movimiento{{ $movements->total() !== 1 ? 's' : '' }}</p>
            </div>

            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Responsable</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                            @php $isEntry = strtolower($mov->tipo) === 'entrada'; @endphp
                            <tr class="group">
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-white text-sm">{{ \Carbon\Carbon::parse($mov->fecha)->translatedFormat('d M, Y') }}</span>
                                        <span class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($mov->fecha)->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td class="font-bold text-white text-sm">{{ $mov->product?->nombre ?? '—' }}</td>
                                <td>
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest
                                        {{ $isEntry ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-red-500/25 bg-red-500/10 text-red-400' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $isEntry ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                        {{ $mov->tipo }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-xl font-black {{ $isEntry ? 'text-emerald-400' : 'text-red-400' }}">
                                        {{ $isEntry ? '+' : '-' }}{{ $mov->cantidad }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        @if($mov->user)
                                            <div class="h-6 w-6 rounded-lg bg-white/5 border border-white/8 flex items-center justify-center text-[9px] font-black text-gold">
                                                {{ strtoupper(mb_substr($mov->user->name, 0, 2)) }}
                                            </div>
                                            <span class="text-sm text-white/80">{{ $mov->user->name }}</span>
                                        @else
                                            <span class="text-muted italic text-xs">Sistema</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-muted italic text-sm">{{ $mov->motivo ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center">
                                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin movimientos</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="space-y-3 md:hidden">
                @forelse($movements as $mov)
                    @php $isEntry = strtolower($mov->tipo) === 'entrada'; @endphp
                    <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <p class="font-bold text-white text-sm">{{ $mov->product?->nombre ?? '—' }}</p>
                                <p class="text-[10px] text-muted">{{ \Carbon\Carbon::parse($mov->fecha)->translatedFormat('d M, Y') }}</p>
                            </div>
                            <span class="text-xl font-black {{ $isEntry ? 'text-emerald-400' : 'text-red-400' }}">
                                {{ $isEntry ? '+' : '-' }}{{ $mov->cantidad }}
                            </span>
                        </div>
                        <div class="border-t border-white/5 pt-3 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-black uppercase {{ $isEntry ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-red-500/25 bg-red-500/10 text-red-400' }}">
                                {{ $mov->tipo }}
                            </span>
                            <p class="text-[10px] text-muted">{{ $mov->user?->name ?? 'Sistema' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center"><p class="text-sm text-muted">Sin movimientos.</p></div>
                @endforelse
            </div>

            <div class="mt-6">{{ $movements->links() }}</div>
        </section>
    </div>
</x-app-layout>
