@php
    $statusConfig = [
        'pendiente'  => ['pill' => 'bg-amber-500/15 text-amber-300 border-amber-500/25',    'dot' => 'bg-amber-400',   'label' => 'Pendiente'],
        'confirmada' => ['pill' => 'bg-blue-500/15 text-blue-300 border-blue-500/25',       'dot' => 'bg-blue-400',    'label' => 'Confirmada'],
        'completada' => ['pill' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25','dot'=> 'bg-emerald-400','label' => 'Completada'],
        'cancelada'  => ['pill' => 'bg-red-500/15 text-red-400 border-red-500/25',          'dot' => 'bg-red-400',     'label' => 'Cancelada'],
        'en_proceso' => ['pill' => 'bg-sky-500/15 text-sky-300 border-sky-500/25',          'dot' => 'bg-sky-400',     'label' => 'En Proceso'],
        'no_asistio' => ['pill' => 'bg-orange-500/15 text-orange-300 border-orange-500/25', 'dot' => 'bg-orange-400',  'label' => 'No Asistió'],
    ];
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);

    // Etiquetas + transiciones validas de la maquina de estados: el dropdown
    // solo ofrece el estado actual + los siguientes permitidos (nada de saltos).
    $estadoLabels = ['pendiente'=>'Pendiente','confirmada'=>'Confirmada','en_proceso'=>'En Proceso','completada'=>'Completada','cancelada'=>'Cancelada','no_asistio'=>'No Asistió'];
    $estadoTransitions = \App\Services\Appointment\AppointmentStatusService::TRANSITIONS;
    $opcionesEstado = fn ($estado) => array_merge([$estado], $estadoTransitions[$estado] ?? []);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Citas & Reservas</h2>
                <p class="ui-subtitle">Gestiona la agenda completa de la barbería.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-walkin'))"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-2.5 text-[11px] font-black uppercase tracking-widest text-emerald-300 hover:bg-emerald-400 hover:text-black transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Walk-in
                </button>
                <a href="{{ route('appointments.create') }}" class="ui-btn">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nueva Cita
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        {{-- ── STATS ROW ──────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach([
                ['label'=>'Total Citas',   'val'=>$stats['total'],      'iconClasses'=>'bg-ink/10 text-ink/60',            'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                ['label'=>'Hoy',           'val'=>$stats['today'],      'iconClasses'=>'bg-blue-500/10 text-blue-400',         'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label'=>'Pendientes',    'val'=>$stats['pendiente'],  'iconClasses'=>'bg-amber-500/10 text-amber-400',       'icon'=>'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['label'=>'Completadas',   'val'=>$stats['completada'], 'iconClasses'=>'bg-emerald-500/10 text-emerald-400',   'icon'=>'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
            ] as $stat)
                <div class="rounded-2xl border border-ink/8 bg-card p-4">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-xl flex items-center justify-center shrink-0 {{ $stat['iconClasses'] }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $stat['icon'] }}"/></svg>
                        </div>
                        <div>
                            <p class="text-xl font-black text-ink leading-none">{{ $stat['val'] }}</p>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-muted mt-0.5">{{ $stat['label'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </section>

        {{-- ── FILTROS AVANZADOS ──────────────────────── --}}
        <section x-data="{ open: {{ count($activeFilters) > 0 ? 'true' : 'false' }} }" class="ui-card-premium overflow-hidden">
            {{-- Header del panel --}}
            <div class="flex items-center justify-between px-6 py-4 cursor-pointer border-b border-ink/6"
                 @click="open = !open">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    <span class="text-sm font-black text-ink uppercase tracking-widest">Filtros Avanzados</span>
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
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" aria-label="Buscar citas por cliente, servicio o barbero"
                                       class="ui-input pl-10" placeholder="Cliente, servicio, barbero...">
                            </div>
                        </div>

                        {{-- Estado --}}
                        <div>
                            <label class="ui-label">Estado</label>
                            <select name="estado" class="ui-input mt-1">
                                <option value="">Todos los estados</option>
                                @foreach($estadoLabels as $val => $lbl)
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
                            <a href="{{ route('appointments.index') }}" class="flex items-center gap-1.5 rounded-xl border border-ink/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-ink hover:border-ink/20 transition-all">
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
                            <x-sortable-th column="fecha">Fecha & Hora</x-sortable-th>
                            <x-sortable-th column="estado">Estado</x-sortable-th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($appointments as $appt)
                            @php $sc = $statusConfig[$appt->estado] ?? ['pill'=>'bg-ink/8 text-ink/50 border-ink/10','dot'=>'bg-ink/30','label'=>$appt->estado]; @endphp
                            <tr class="group">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="h-8 w-8 rounded-xl bg-ink/5 border border-ink/8 flex items-center justify-center text-[10px] font-black text-gold shrink-0">
                                            {{ strtoupper(mb_substr($appt->client?->user?->name ?? 'C', 0, 2)) }}
                                        </div>
                                        <span class="font-bold text-ink text-sm">{{ $appt->client?->user?->name ?? 'Desconocido' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-sm text-muted">{{ $appt->service?->nombre ?? '—' }}</span>
                                </td>
                                <td>
                                    @if($appt->barber?->user)
                                        <div class="flex items-center gap-2">
                                            <div class="h-6 w-6 rounded-lg bg-gold/10 border border-gold/15 flex items-center justify-center text-[9px] font-black text-gold">
                                                {{ strtoupper(mb_substr($appt->barber->user->name, 0, 2)) }}
                                            </div>
                                            <span class="text-sm text-ink/80">{{ $appt->barber->user->name }}</span>
                                        </div>
                                    @else
                                        <span class="text-muted italic text-xs">Sin asignar</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span class="font-bold text-ink text-sm">{{ \Carbon\Carbon::parse($appt->fecha)->translatedFormat('d M, Y') }}</span>
                                        <span class="text-[10px] text-muted font-bold">{{ substr($appt->hora_inicio, 0, 5) }} – {{ substr($appt->hora_fin, 0, 5) }}</span>
                                    </div>
                                </td>
                                <td>
                                    {{-- Estado: solo transiciones validas (o badge fijo si es terminal) --}}
                                    @php $opsT = $opcionesEstado($appt->estado); @endphp
                                    @if(count($opsT) > 1)
                                        <form method="POST" action="{{ route('appointments.update-status', $appt) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <select name="estado" onchange="this.form.submit()"
                                                    class="rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest bg-transparent cursor-pointer transition-all hover:opacity-80 {{ $sc['pill'] }}"
                                                    title="Cambiar estado" aria-label="Cambiar estado de la cita">
                                                @foreach($opsT as $val)
                                                    <option value="{{ $val }}" @selected($appt->estado === $val)
                                                            class="bg-card text-ink normal-case tracking-normal font-bold">
                                                        {{ $estadoLabels[$val] ?? $val }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span class="inline-block rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $sc['pill'] }}">{{ $estadoLabels[$appt->estado] ?? $appt->estado }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-2 opacity-60 group-hover:opacity-100 transition-opacity">
                                        @if(!in_array($appt->estado, ['completada', 'cancelada', 'no_asistio']))
                                            {{-- Cobrar y completar en un paso: PaymentService::create ya
                                                 marca la cita como completada y genera el recibo PDF --}}
                                            <button type="button"
                                                    onclick='window.dispatchEvent(new CustomEvent("open-cobro", { detail: {
                                                        id: @json((string) $appt->id),
                                                        cliente: @json($appt->client?->user?->name ?? "Cliente"),
                                                        servicio: @json($appt->service?->nombre ?? "—"),
                                                        monto: @json((float) ($appt->precio_cobrado ?? $appt->service?->precio ?? 0)),
                                                        nivelPct: @json(\App\Services\Loyalty\LoyaltyService::discountPct($appt->client?->nivel ?? "nuevo")),
                                                        nivelLabel: @json(\App\Services\Loyalty\LoyaltyService::LEVEL_LABELS[$appt->client?->nivel ?? "nuevo"]),
                                                        puntos: @json((int) ($appt->client?->puntos ?? 0))
                                                    }}))'
                                                    class="h-8 w-8 rounded-lg border border-emerald-500/25 bg-emerald-500/5 flex items-center justify-center text-emerald-400/80 hover:text-emerald-300 hover:border-emerald-500/50 hover:bg-emerald-500/10 transition-all" title="Cobrar y completar">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                                            </button>
                                        @endif
                                        <a href="{{ route('appointments.edit', $appt) }}"
                                           class="h-8 w-8 rounded-lg border border-ink/10 bg-ink/5 flex items-center justify-center text-muted hover:text-gold hover:border-gold/30 transition-all" title="Editar">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <form action="{{ route('appointments.destroy', $appt) }}" method="POST"
                                              onsubmit="return confirm('¿Cancelar esta cita?');" class="inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" aria-label="Cancelar cita"
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
                                        <svg class="h-12 w-12 text-ink/5 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
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
                    @php $sc = $statusConfig[$appt->estado] ?? ['pill'=>'bg-ink/8 text-ink/50 border-ink/10','dot'=>'bg-ink/30','label'=>$appt->estado]; @endphp
                    <div class="rounded-2xl border border-ink/8 bg-card p-4">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-xl bg-gold/10 border border-gold/15 flex items-center justify-center text-sm font-black text-gold">
                                    {{ strtoupper(mb_substr($appt->client?->user?->name ?? 'C', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-black text-ink">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                                    <p class="text-[10px] font-bold text-gold uppercase tracking-wider">{{ $appt->service?->nombre ?? '—' }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-wider {{ $sc['pill'] }}">
                                <span class="h-1 w-1 rounded-full {{ $sc['dot'] }}"></span>
                                {{ $sc['label'] }}
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs border-t border-ink/5 pt-3">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-muted mb-0.5">Fecha</p>
                                <p class="font-bold text-ink">{{ \Carbon\Carbon::parse($appt->fecha)->translatedFormat('d M, Y') }}</p>
                                <p class="text-muted">{{ substr($appt->hora_inicio, 0, 5) }} – {{ substr($appt->hora_fin, 0, 5) }}</p>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-muted mb-0.5">Barbero</p>
                                <p class="font-bold text-ink">{{ $appt->barber?->user?->name ?? 'Sin asignar' }}</p>
                            </div>
                        </div>
                        {{-- Estado inline (mobile): solo transiciones validas --}}
                        @php $opsC = $opcionesEstado($appt->estado); @endphp
                        @if(count($opsC) > 1)
                        <form method="POST" action="{{ route('appointments.update-status', $appt) }}" class="mt-3">
                            @csrf @method('PATCH')
                            <div class="flex gap-2">
                                <select name="estado" class="flex-1 h-9 rounded-xl border border-ink/10 bg-black/40 px-3 text-[10px] font-black uppercase tracking-wider text-ink focus:border-gold/50 focus:outline-none transition-all">
                                    @foreach($opsC as $val)
                                        <option value="{{ $val }}" @selected($appt->estado === $val) class="bg-card normal-case font-bold">{{ $estadoLabels[$val] ?? $val }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="h-9 px-3 rounded-xl border border-gold/30 bg-gold/10 text-[10px] font-black uppercase text-gold hover:bg-gold hover:text-black transition-all shrink-0">OK</button>
                            </div>
                        </form>
                        @endif
                        <div class="mt-3 flex justify-end gap-3 border-t border-ink/5 pt-3">
                            <a href="{{ route('appointments.edit', $appt) }}" class="text-[10px] font-black uppercase tracking-widest text-gold hover:text-ink transition">Editar</a>
                            <form action="{{ route('appointments.destroy', $appt) }}" method="POST" onsubmit="return confirm('¿Cancelar?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[10px] font-black uppercase tracking-widest text-red-500 hover:text-red-400 transition">Anular</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-ink/10 p-12 text-center">
                        <p class="text-sm text-muted">Sin citas que mostrar.</p>
                    </div>
                @endforelse
            </div>

            {{-- Paginación --}}
            <div class="mt-6">{{ $appointments->links() }}</div>
        </section>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         MODAL WALK-IN — registro rápido de cliente sin cita previa.
         3 campos: cliente (búsqueda por teléfono/nombre), servicio, barbero.
         Crea la cita para AHORA en estado en_proceso.
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-data="walkinModal()"
         x-show="open"
         x-cloak
         @open-walkin.window="open = true; $nextTick(() => $refs.searchInput.focus())"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display: none;">
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="open = false"></div>

        <div class="relative w-full max-w-lg rounded-3xl border border-ink/10 bg-card p-6 shadow-2xl"
             x-show="open" x-transition>
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-ink uppercase tracking-tight">Walk-in</h3>
                    <p class="text-[11px] text-muted mt-0.5">Cliente en recepción — el servicio inicia ahora mismo.</p>
                </div>
                <button type="button" @click="open = false"
                        class="h-8 w-8 rounded-lg border border-ink/10 text-muted hover:text-ink transition flex items-center justify-center">&times;</button>
            </div>

            @if($errors->has('walkin'))
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-xs font-bold text-red-300">
                    {{ $errors->first('walkin') }}
                </div>
            @endif

            <form method="POST" action="{{ route('appointments.walk-in') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="client_id" :value="selectedClient?.id">

                {{-- 1. Cliente: búsqueda por teléfono o nombre --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">1 · Cliente (teléfono o nombre)</label>
                    <template x-if="!selectedClient">
                        <div class="relative">
                            <input type="text" x-ref="searchInput" x-model="query"
                                   @input.debounce.300ms="search()"
                                   placeholder="Ej. 5512345678 o Juan..."
                                   class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-4 text-sm text-ink placeholder-ink/25 focus:border-gold/50 focus:outline-none">
                            <div x-show="results.length > 0"
                                 class="absolute z-10 mt-1 w-full rounded-xl border border-ink/10 bg-card shadow-xl overflow-hidden">
                                <template x-for="c in results" :key="c.id">
                                    <button type="button" @click="selectedClient = c; results = []; query = ''"
                                            class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-gold/10 transition">
                                        <span class="text-sm font-bold text-ink" x-text="c.name"></span>
                                        <span class="text-[10px] text-muted" x-text="c.telefono"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="searched && results.length === 0 && query.length >= 2"
                               class="mt-1.5 text-[10px] text-muted italic">Sin resultados — verifica el teléfono o registra al cliente primero.</p>
                        </div>
                    </template>
                    <template x-if="selectedClient">
                        <div class="flex items-center justify-between rounded-xl border border-emerald-500/25 bg-emerald-500/8 px-4 py-2.5">
                            <div>
                                <span class="text-sm font-black text-emerald-300" x-text="selectedClient.name"></span>
                                <span class="ml-2 text-[10px] text-muted" x-text="selectedClient.telefono"></span>
                            </div>
                            <button type="button" @click="selectedClient = null"
                                    class="text-[10px] font-black uppercase text-muted hover:text-ink transition">Cambiar</button>
                        </div>
                    </template>
                </div>

                {{-- 2. Servicio --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">2 · Servicio</label>
                    <select name="service_id" required
                            class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-3 text-sm text-ink focus:border-gold/50 focus:outline-none">
                        <option value="">Selecciona…</option>
                        @foreach($services as $service)
                            <option value="{{ $service->id }}">
                                {{ $service->nombre }} — ${{ number_format((float) $service->precio, 0) }} · {{ $service->duracion_min }} min
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- 3. Barbero --}}
                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">3 · Barbero</label>
                    <select name="barber_id" required
                            class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-3 text-sm text-ink focus:border-gold/50 focus:outline-none">
                        <option value="">Selecciona…</option>
                        @foreach($barbers as $barber)
                            <option value="{{ $barber->id }}">{{ $barber->user?->name ?? 'Barbero' }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" :disabled="!selectedClient"
                        :class="selectedClient ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300 hover:bg-emerald-400 hover:text-black' : 'bg-ink/5 border-ink/10 text-muted cursor-not-allowed'"
                        class="w-full h-12 rounded-xl border text-[11px] font-black uppercase tracking-widest transition-all">
                    Iniciar servicio ahora &rarr;
                </button>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         MODAL COBRAR — registra el pago de una cita y la marca completada
         en un solo paso (PaymentService genera además el recibo PDF).
    ══════════════════════════════════════════════════════════════════ --}}
    <div x-data="cobroModal()"
         x-show="open"
         x-cloak
         @open-cobro.window="show($event.detail)"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display: none;">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="open = false"></div>

        <div class="relative w-full max-w-md rounded-3xl border border-ink/10 bg-card p-6 shadow-2xl"
             x-show="open" x-transition>
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-black text-ink uppercase tracking-tight">Cobrar y completar</h3>
                    <p class="text-[11px] text-muted mt-0.5">
                        <span class="text-ink font-bold" x-text="appt.cliente"></span>
                        · <span x-text="appt.servicio"></span>
                    </p>
                </div>
                <button type="button" @click="open = false"
                        class="h-8 w-8 rounded-lg border border-ink/10 text-muted hover:text-ink transition flex items-center justify-center">&times;</button>
            </div>

            <form method="POST" action="{{ route('payments.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="appointment_id" :value="appt.id">
                <input type="hidden" name="stay" value="1">

                <div>
                    <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">Monto <span class="normal-case font-normal">(precio real, no editable)</span></label>
                    <input type="number" name="monto" step="0.01" min="0.01" required readonly x-model="appt.monto"
                           class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-4 text-sm text-ink/70 cursor-not-allowed focus:outline-none">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">Método</label>
                        <select name="metodo_pago" required
                                class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-3 text-sm text-ink focus:border-gold/50 focus:outline-none">
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia</option>
                        </select>
                        <p class="mt-1 text-[9px] text-muted italic">¿Cobras con tarjeta? Usa el <a href="{{ route('payments.create') }}" class="underline hover:text-gold">formulario completo</a> (beta).</p>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black uppercase tracking-widest text-muted mb-1.5">Propina (opcional)</label>
                        <input type="number" name="propina" step="0.01" min="0" placeholder="0" x-model="propina"
                               class="w-full h-11 rounded-xl border border-ink/10 bg-black/40 px-4 text-sm text-ink placeholder-ink/25 focus:border-gold/50 focus:outline-none">
                    </div>
                </div>

                <div x-show="appt.nivelLabel" x-cloak class="space-y-2 rounded-xl border border-gold/20 bg-ink/5 p-3">
                    <p class="text-[10px] font-black uppercase tracking-widest text-gold">
                        <span x-text="appt.nivelLabel"></span>
                        <span x-show="appt.nivelPct > 0" x-text="' · ' + appt.nivelPct + '% desc.'"></span>
                    </p>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-muted mb-1"
                               x-text="'Canjear puntos (disp. ' + appt.puntos + ', máx. ' + maxPuntosCanjeables + ')'"></label>
                        <input type="number" name="puntos_canjeados" step="1" min="0" :max="maxPuntosCanjeables" x-model="puntosCanjear"
                               class="w-full h-10 rounded-lg border border-ink/10 bg-black/40 px-3 text-sm text-ink focus:border-gold/50 focus:outline-none">
                    </div>
                    <p class="text-[10px] text-muted">Total con descuentos: <span class="text-ink font-black" x-text="'$' + total.toFixed(2)"></span></p>
                </div>

                <button type="submit"
                        class="w-full h-12 rounded-xl border border-emerald-500/40 bg-emerald-500/15 text-[11px] font-black uppercase tracking-widest text-emerald-300 hover:bg-emerald-400 hover:text-black transition-all">
                    Registrar pago y completar &rarr;
                </button>
                <p class="text-center text-[10px] text-muted">Se generará el recibo PDF automáticamente.</p>
            </form>
        </div>
    </div>

    <script>
        function cobroModal() {
            return {
                open: false,
                appt: { id: null, cliente: '', servicio: '', monto: 0, nivelPct: 0, nivelLabel: '', puntos: 0 },
                propina: 0,
                puntosCanjear: 0,
                show(detail) {
                    this.appt = detail;
                    this.propina = 0;
                    this.puntosCanjear = 0;
                    this.open = true;
                },
                get montoConNivel() { return (parseFloat(this.appt.monto) || 0) * (1 - (this.appt.nivelPct || 0) / 100) },
                get maxPuntosCanjeables() { return Math.max(0, Math.min(this.appt.puntos || 0, Math.floor(this.montoConNivel * 0.5))) },
                get descuentoPuntos() { return Math.min(parseInt(this.puntosCanjear) || 0, this.maxPuntosCanjeables) },
                get total() { return Math.max(0, this.montoConNivel - this.descuentoPuntos) + (parseFloat(this.propina) || 0) },
            };
        }

        function walkinModal() {
            return {
                open: {{ $errors->has('walkin') ? 'true' : 'false' }},
                query: '',
                results: [],
                searched: false,
                selectedClient: null,

                async search() {
                    this.searched = false;
                    if (this.query.trim().length < 2) { this.results = []; return; }
                    try {
                        const res = await fetch(`{{ route('appointments.walk-in.clients') }}?q=${encodeURIComponent(this.query.trim())}`, {
                            headers: { 'Accept': 'application/json' },
                        });
                        if (!res.ok) throw new Error(`HTTP ${res.status}`);
                        const data = await res.json();
                        this.results = data.clients;
                    } catch (e) {
                        this.results = [];
                    } finally {
                        this.searched = true;
                    }
                },
            };
        }
    </script>
</x-app-layout>
