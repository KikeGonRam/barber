@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
    $metodoPagoLabels = ['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','transferencia'=>'Transferencia','otro'=>'Otro'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Centro de <span class="text-gold">Facturación</span></h2>
                <p class="ui-subtitle">Gestión de ingresos, comprobantes y trazabilidad financiera.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('payments.pending') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest relative">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Por Revisar
                    @if(($pendingCount ?? 0) > 0)
                        <span class="ml-1.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('reports.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Reportes
                </a>
                <a href="{{ route('payments.create') }}" class="ui-btn">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nuevo Cobro
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">Facturación Hoy</p>
                <p class="text-2xl font-black text-emerald-400">${{ number_format($stats['total_hoy'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">Este Mes</p>
                <p class="text-2xl font-black text-white">${{ number_format($stats['total_mes'], 2) }}</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">Total Cobros</p>
                <p class="text-2xl font-black text-white">{{ $stats['count'] }}</p>
            </div>
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-2">Por Método</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($stats['metodos'] as $metodo => $cnt)
                        <span class="text-[9px] font-black bg-white/8 border border-white/10 rounded-full px-2 py-0.5 text-white/70 uppercase">
                            {{ $metodo }}: {{ $cnt }}
                        </span>
                    @endforeach
                </div>
            </div>
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
                <form method="GET" action="{{ route('payments.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Cliente o servicio...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Método de Pago</label>
                            <select name="metodo_pago" class="ui-input mt-1">
                                <option value="">Todos los métodos</option>
                                @foreach($metodoPagoLabels as $val => $lbl)
                                    <option value="{{ $val }}" @selected(($filters['metodo_pago'] ?? '') === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Barbero</label>
                            <select name="barbero_id" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($barbers as $b)
                                    <option value="{{ $b->id }}" @selected(($filters['barbero_id'] ?? '') == $b->id)>{{ $b->user?->name }}</option>
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
                            <a href="{{ route('payments.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
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
                        @php $labels = ['q'=>'Búsqueda','metodo_pago'=>'Método','barbero_id'=>'Barbero','fecha_desde'=>'Desde','fecha_hasta'=>'Hasta']; @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $labels[$key] ?? $key }}: {{ $key === 'barbero_id' ? ($barbers->firstWhere('id', $val)?->user?->name ?? $val) : $val }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ─────────────────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">
                    {{ $payments->total() }} comprobante{{ $payments->total() !== 1 ? 's' : '' }}
                </p>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 md:hidden">
                @forelse($payments as $payment)
                <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="text-xs font-black text-white">#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</p>
                            <p class="text-[10px] text-muted">{{ $payment->created_at?->translatedFormat('d M, Y · H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xl font-black text-emerald-400">${{ number_format($payment->monto + $payment->propina, 2) }}</p>
                            @if($payment->propina > 0)
                                <p class="text-[9px] font-bold text-gold">+${{ number_format($payment->propina, 2) }} propina</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="h-9 w-9 rounded-xl bg-gold/10 border border-gold/15 flex items-center justify-center text-sm font-black text-gold shrink-0">
                            {{ strtoupper(mb_substr($payment->appointment?->client?->user?->name ?? 'C', 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-white text-sm truncate">{{ $payment->appointment?->client?->user?->name ?? 'N/A' }}</p>
                            <p class="text-[10px] font-bold text-gold uppercase tracking-widest truncate">{{ $payment->appointment?->service?->nombre ?? 'General' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-white/5 pt-3 gap-3">
                        <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-muted shrink-0">
                            {{ $metodoPagoLabels[$payment->metodo_pago] ?? $payment->metodo_pago }}
                        </span>
                        <div class="flex items-center gap-4">
                            <a href="{{ route('payments.receipt.download', $payment) }}"
                               class="flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                PDF
                            </a>
                            <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                  onsubmit="return confirm('¿Anular comprobante #{{ str_pad($payment->id,6,'0',STR_PAD_LEFT) }}?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 transition">Anular</button>
                            </form>
                        </div>
                    </div>
                </div>
                @empty
                <div class="rounded-2xl border border-dashed border-white/10 p-10 text-center">
                    <p class="text-sm text-muted">Sin comprobantes.</p>
                </div>
                @endforelse
            </div>

            {{-- Desktop table --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <x-sortable-th column="created_at">Folio / Fecha</x-sortable-th>
                            <th>Cliente & Servicio</th>
                            <th>Barbero</th>
                            <x-sortable-th column="metodo_pago">Método</x-sortable-th>
                            <x-sortable-th column="monto_total" align="right">Total</x-sortable-th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr class="group">
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-black text-white text-xs">#{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</span>
                                        <span class="text-[10px] text-muted font-bold uppercase">{{ $payment->created_at?->translatedFormat('d M, Y · H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="text-white font-bold text-sm">{{ $payment->appointment?->client?->user?->name ?? 'N/A' }}</span>
                                        <span class="text-[10px] uppercase tracking-widest text-gold font-black">{{ $payment->appointment?->service?->nombre ?? 'General' }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if($payment->appointment?->barber?->user)
                                        <div class="flex items-center gap-2">
                                            <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/15 flex items-center justify-center text-[9px] font-black text-gold">
                                                {{ strtoupper(mb_substr($payment->appointment->barber->user->name, 0, 2)) }}
                                            </div>
                                            <span class="text-sm text-white/80">{{ $payment->appointment->barber->user->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted text-xs italic">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="inline-flex items-center rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-widest text-muted">
                                        {{ $metodoPagoLabels[$payment->metodo_pago] ?? $payment->metodo_pago }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-black text-emerald-400 text-base">${{ number_format($payment->monto + $payment->propina, 2) }}</span>
                                        @if($payment->propina > 0)
                                            <span class="text-[9px] text-gold font-bold uppercase">+ ${{ number_format($payment->propina, 2) }} propina</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('payments.receipt.download', $payment) }}"
                                           class="h-8 px-3 rounded-lg border border-white/10 bg-white/5 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold hover:border-gold/30 transition-all">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            PDF
                                        </a>
                                        <form action="{{ route('payments.destroy', $payment) }}" method="POST"
                                              onsubmit="return confirm('¿Anular comprobante #{{ $payment->id }}?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Anular comprobante" class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:bg-red-500/10 transition-all">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="h-12 w-12 text-white/5 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin comprobantes</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>{{-- /desktop table --}}
            <div class="mt-6">{{ $payments->links() }}</div>
        </section>
    </div>
</x-app-layout>
