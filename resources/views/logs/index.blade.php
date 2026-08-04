@php
    $activeFilters = array_filter($filters ?? [], fn($v) => $v !== '' && $v !== null);
    $eventConfig = [
        'created' => ['icon' => 'M12 4v16m8-8H4',        'label' => 'Creado',
            'ring' => 'hover:border-emerald-500/25 hover:bg-emerald-500/[0.02]',
            'iconBg' => 'bg-emerald-500/10 text-emerald-400',
            'badge' => 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400'],
        'updated' => ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => 'Actualizado',
            'ring' => 'hover:border-blue-500/25 hover:bg-blue-500/[0.02]',
            'iconBg' => 'bg-blue-500/10 text-blue-400',
            'badge' => 'border-blue-500/25 bg-blue-500/10 text-blue-400'],
        'deleted' => ['icon' => 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', 'label' => 'Eliminado',
            'ring' => 'hover:border-red-500/25 hover:bg-red-500/[0.02]',
            'iconBg' => 'bg-red-500/10 text-red-400',
            'badge' => 'border-red-500/25 bg-red-500/10 text-red-400'],
    ];
    $defaultEventConfig = ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'ring' => 'hover:border-white/25 hover:bg-white/[0.02]',
        'iconBg' => 'bg-white/10 text-white/40',
        'badge' => 'border-white/25 bg-white/10 text-white/50'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Auditoría de <span class="text-gold">Actividad</span></h2>
                <p class="ui-subtitle">Registro cronológico de todas las operaciones del sistema.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

        {{-- ── STATS ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-muted mb-1">Total</p>
                <p class="text-2xl font-black text-white">{{ $stats['total'] }}</p>
            </div>
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-blue-400/70 mb-1">Hoy</p>
                <p class="text-2xl font-black text-blue-400">{{ $stats['hoy'] }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-emerald-400/70 mb-1">Creaciones</p>
                <p class="text-2xl font-black text-emerald-400">{{ $stats['creates'] }}</p>
            </div>
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-blue-400/70 mb-1">Actualizaciones</p>
                <p class="text-2xl font-black text-blue-400">{{ $stats['updates'] }}</p>
            </div>
            <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-4">
                <p class="text-[10px] font-black uppercase tracking-wider text-red-400/70 mb-1">Eliminaciones</p>
                <p class="text-2xl font-black text-red-400">{{ $stats['deletes'] }}</p>
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
                <form method="GET" action="{{ route('logs.index') }}">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="lg:col-span-2">
                            <label class="ui-label">Búsqueda</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input pl-10" placeholder="Descripción del evento...">
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Módulo</label>
                            <select name="log_name" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($logNames as $name)
                                    <option value="{{ $name }}" @selected(($filters['log_name'] ?? '') === $name)>{{ ucfirst($name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Tipo de evento</label>
                            <select name="event" class="ui-input mt-1">
                                <option value="">Todos</option>
                                @foreach($events as $ev)
                                    <option value="{{ $ev }}" @selected(($filters['event'] ?? '') === $ev)>{{ ucfirst($ev) }}</option>
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
                            <a href="{{ route('logs.index') }}" class="flex items-center gap-1.5 rounded-xl border border-white/10 px-4 py-2.5 text-[11px] font-black uppercase tracking-widest text-muted hover:text-white transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                Limpiar
                            </a>
                        @endif
                    </div>
                </form>
            </div>
        </section>

        {{-- ── LISTA DE EVENTOS ─────────────────────────── --}}
        <section>
            <div class="mb-3 px-1">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $logs->total() }} evento{{ $logs->total() !== 1 ? 's' : '' }}</p>
            </div>

            <div class="space-y-2">
                @forelse($logs as $log)
                    @php
                        $ec = ($eventConfig[$log->event ?? ''] ?? $defaultEventConfig) + ['label' => $log->event ?? 'Sistema'];
                    @endphp
                    <div class="group flex items-start gap-4 rounded-2xl border border-white/6 bg-[#111] p-4 {{ $ec['ring'] }} transition-all duration-300">
                        {{-- Icono de tipo evento --}}
                        <div class="h-10 w-10 rounded-xl flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform {{ $ec['iconBg'] }}">
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ec['icon'] }}"/>
                            </svg>
                        </div>

                        {{-- Contenido --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <h3 class="text-sm font-black text-white">{{ $log->description ?: 'Operación del Sistema' }}</h3>
                                @if($log->log_name)
                                    <span class="text-[9px] font-black border border-white/10 bg-white/5 rounded-full px-2 py-0.5 uppercase tracking-wider text-muted">
                                        {{ $log->log_name }}
                                    </span>
                                @endif
                                <span class="text-[9px] font-black border rounded-full px-2 py-0.5 uppercase tracking-wider {{ $ec['badge'] }}">
                                    {{ $ec['label'] }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-x-5 gap-y-1 text-[10px] font-bold text-muted uppercase tracking-wider">
                                <span>
                                    <span class="text-gold/50">Por:</span>
                                    {{ $log->causer?->name ?: 'Sistema Autónomo' }}
                                </span>
                                @if($log->subject_type)
                                    <span>
                                        <span class="text-gold/50">Objeto:</span>
                                        {{ class_basename((string) $log->subject_type) }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Fecha --}}
                        <div class="text-right shrink-0">
                            <p class="text-xs font-black text-white">{{ $log->created_at?->format('H:i:s') }}</p>
                            <p class="text-[10px] text-muted uppercase tracking-wider mt-0.5">{{ $log->created_at?->translatedFormat('d M, Y') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-white/10 py-20 text-center">
                        <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin registros de actividad</p>
                    </div>
                @endforelse

                <div class="mt-6">{{ $logs->links() }}</div>
            </div>
        </section>
    </div>
</x-app-layout>
