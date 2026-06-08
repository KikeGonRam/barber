@php
    $statusConfig = [
        'pendiente'  => ['pill' => 'bg-amber-500/15 text-amber-300 border-amber-500/25',    'dot' => 'bg-amber-400',   'label' => 'Pendiente'],
        'confirmada' => ['pill' => 'bg-blue-500/15 text-blue-300 border-blue-500/25',       'dot' => 'bg-blue-400',    'label' => 'Confirmada'],
        'completada' => ['pill' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25','dot'=> 'bg-emerald-400','label' => 'Completada'],
        'cancelada'  => ['pill' => 'bg-red-500/15 text-red-400 border-red-500/25',          'dot' => 'bg-red-400',     'label' => 'Cancelada'],
        'en_proceso' => ['pill' => 'bg-sky-500/15 text-sky-300 border-sky-500/25',          'dot' => 'bg-sky-400',     'label' => 'En Proceso'],
    ];
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Citas & Reservas</h2>
                <p class="ui-subtitle">Gestiona la agenda completa de la barbería.</p>
            </div>
            <a href="{{ route('appointments.create') }}" class="ui-btn">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nueva Cita
            </a>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ROW ──────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach([
                ['label'=>'Total Citas',   'val'=>$stats['total'],      'color'=>'white',   'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['label'=>'Hoy',           'val'=>$stats['today'],      'color'=>'blue',    'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label'=>'Pendientes',    'val'=>$stats['pendiente'],  'color'=>'amber',   'icon'=>'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label'=>'Completadas',   'val'=>$stats['completada'], 'color'=>'emerald', 'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ] as $stat)
                <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl bg-{{ $stat['color'] }}-500/10 text-{{ $stat['color'] === 'white' ? 'white/60' : $stat['color'].'-400' }} flex items-center justify-center shrink-0">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/></svg>
                        </div>
                        <div>
                            <p class="text-xl font-black text-white leading-none">{{ $stat['val'] }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted mt-0.5">{{ $stat['label'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ── FILTROS AVANZADOS ──────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            {{-- Header del panel --}}
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-white/6"
                 @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-white uppercase tracking-widest">Filtros Avanzados</span>
                    @if(count($activeFilters) > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-gold text-[9px] font-black text-black">{{ count($activeFilters) }}</span>
                    @endif
                </div>
                <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-muted transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>

            {{-- Body de filtros --}}
            <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="px-6 py-5">
                <form method="GET" action="{{ route('appointments.index') }}" id="filter-form">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

                        {{-- Búsqueda --}}
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}"
                                       class="ui-input pl-10" placeholder="Cliente, servicio, barbero...">
                            </div>
                        </div>

                        {{-- Estado --}}
                        <div>
                            <label class="ui-label">Estado</label>
                            <select name="estado" class="ui-input mt-1">
                                <option value="">Todos los estados</option>
                                @foreach(['pendiente'=>'Pendiente','confirmada'=>'Confirmada','completada'=>'Completada','cancelada'=>'Cancelada','en_proceso'=>'En Proceso'] as $val => $lbl)
                                    <option value="{{ $val }}" @selected(($filters['estado'] ?? '') === $val)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Barbero --}}
                        <div>
                            <label class="ui-label">Barbero</label>
                            <select name="barber_id" class="ui-input mt-1">
                                <option value="">Todos los barberos</option>
                                @foreach($barbers as $barber)
                                    <option value="{{ $barber->id }}" @selected(($filters['barber_id'] ?? '') == $barber->id)>
                                        {{ $barber->user?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Fecha desde --}}
                        <div>
                            <label class="ui-label">Fecha desde</label>
                            <input type="date" name="fecha_desde" value="{{ $filters['fecha_desde'] ?? '' }}" class="ui-input mt-1">
                        </div>

                        {{-- Fecha hasta --}}
                        <div>
                            <label class="ui-label">Fecha hasta</label>
                            <input type="date" name="fecha_hasta" value="{{ $filters['fecha_hasta'] ?? '' }}" class="ui-input mt-1">
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <button type="submit" class="ui-btn py-2.5 px-6 text-[11px] tracking-widest">
                            <svg class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            Aplicar Filtros
                        </button>
                        @if(count($activeFilters) > 0)
                            <a href="{{ route('appointments.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white hover:border-white/20 transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Active filter chips --}}
            @if(count($activeFilters) > 0)
                <div class="flex flex-wrap gap-2 px-6 pb-4">
                    @foreach($activeFilters as $key => $val)
                        @php
                            $labels = ['q'=>'Búsqueda','estado'=>'Estado','barber_id'=>'Barbero','fecha_desde'=>'Desde','fecha_hasta'=>'Hasta'];
                            $display = $labels[$key] ?? $key;
                        @endphp
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/25 bg-gold/8 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-gold">
                            {{ $display }}: {{ $key === 'barber_id' ? ($barbers->firstWhere('id', $val)?->user?->name ?? $val) : $val }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ── TABLA ──────────────────────────────────── --}}
        <section>
            {{-- Contador de resultados --}}
            <div class="mb-3 flex items-center justify-between px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">
                    {{ $appointments->total() }} resultado{{ $appointments->total() !== 1 ? 's' : '' }}
                    @if(count($activeFilters) > 0) <span class="text-gold">(filtrado{{ count($activeFilters) > 1 ? 's' : '' }})</span>@endif
                </p>
            </div>

            {{-- Desktop --}}
            <div class="hidden md:block ui-table-container">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Barbero</th>
                            <th>Fecha & Hora</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($appointments as $appt)
                            @php $sc = $statusConfig[$appt->estado] ?? ['pill'=>'bg-white/8 text-white/50 border-white/10','dot'=>'bg-white/30','label'=>$appt->estado]; @endphp
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center text-[10px] font-black text-gold shrink-0">
                                            {{ strtoupper(substr($appt->client?->user?->name ?? 'C', 0, 2)) }}
                                        </div>
                                        <span class="font-bold text-white text-sm">{{ $appt->client?->user?->name ?? 'Desconocido' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-sm text-muted">{{ $appt->service?->nombre ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($appt->barber?->user)
                                        <div class="flex items-center gap-2">
                                            <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/15 flex items-center justify-center text-[9px] font-black text-gold">
                                                {{ strtoupper(substr($appt->barber->user->name, 0, 2)) }}
                                            </div>
                                            <span class="text-sm text-white/80">{{ $appt->barber->user->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted italic text-xs">Sin asignar</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-white text-sm">{{ \Carbon\Carbon::parse($appt->fecha)->format('d M, Y') }}</span>
                                        <span class="text-[10px] text-muted font-bold">{{ substr($appt->hora_inicio, 0, 5) }} – {{ substr($appt->hora_fin, 0, 5) }}</span>
                                    </div>
                                </td>
                                <td>
                                    {{-- Dropdown inline para cambiar estado --}}
                                    <form method="POST" action="{{ route('appointments.update-status', $appt) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <select name="estado" onchange="this.form.submit()"
                                                class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest bg-transparent cursor-pointer transition-all hover:opacity-80 {{ $sc['pill'] }}"
                                                title="Cambiar estado">
                                            @foreach(['pendiente'=>'Pendiente','confirmada'=>'Confirmada','en_proceso'=>'En Proceso','completada'=>'Completada','cancelada'=>'Cancelada','no_asistio'=>'No Asistió'] as $val => $lbl)
                                                <option value="{{ $val }}" @selected($appt->estado === $val)
                                                        class="bg-[#111] text-white normal-case tracking-normal font-bold">
                                                    {{ $lbl }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="{{ route('appointments.edit', $appt) }}"
                                           class="h-8 w-8 rounded-lg border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all" title="Editar">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('appointments.destroy', $appt) }}" method="POST"
                                              onsubmit="return confirm('¿Cancelar esta cita?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                    class="h-8 w-8 rounded-lg border border-red-500/20 bg-red-500/5 flex items-center justify-center text-red-500/70 hover:text-red-400 hover:border-red-500/40 hover:bg-red-500/10 transition-all" title="Cancelar">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="h-12 w-12 text-white/5 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin resultados</p>
                                        <p class="text-xs text-muted/60 mt-1">Prueba ajustando los filtros de búsqueda</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards --}}
            <div class="space-y-3 md:hidden">
                @forelse($appointments as $appt)
                    @php $sc = $statusConfig[$appt->estado] ?? ['pill'=>'bg-white/8 text-white/50 border-white/10','dot'=>'bg-white/30','label'=>$appt->estado]; @endphp
                    <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-gold/10 border border-gold/15 flex items-center justify-center text-sm font-black text-gold">
                                    {{ strtoupper(substr($appt->client?->user?->name ?? 'C', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-black text-white">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                                    <p class="text-[10px] font-bold text-gold uppercase tracking-wider">{{ $appt->service?->nombre ?? '—' }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $sc['pill'] }}">
                                <span class="h-1 w-1 rounded-full {{ $sc['dot'] }}"></span>
                                {{ $sc['label'] }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs border-t border-white/5 pt-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-muted mb-0.5">Fecha</p>
                                <p class="font-bold text-white">{{ \Carbon\Carbon::parse($appt->fecha)->format('d/m/Y') }}</p>
                                <p class="text-muted">{{ substr($appt->hora_inicio, 0, 5) }} – {{ substr($appt->hora_fin, 0, 5) }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-muted mb-0.5">Barbero</p>
                                <p class="font-bold text-white">{{ $appt->barber?->user?->name ?? 'Sin asignar' }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end gap-3 border-t border-white/5 pt-3">
                            <a href="{{ route('appointments.edit', $appt) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Editar</a>
                            <form action="{{ route('appointments.destroy', $appt) }}" method="POST" onsubmit="return confirm('¿Cancelar?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 transition">Cancelar</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 p-12 text-center">
                        <p class="text-sm text-muted">Sin citas que mostrar.</p>
                    </div>
                @endforelse
            </div>

            {{-- Paginación --}}
            <div class="mt-6">{{ $appointments->links() }}</div>
        </section>
    </div>
</x-app-layout>
