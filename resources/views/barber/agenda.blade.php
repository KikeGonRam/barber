<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Agenda</span></h2>
                <p class="ui-subtitle">{{ $period === 'week' ? 'Semana del '.$baseDate->copy()->startOfWeek()->format('d M').' al '.$baseDate->copy()->endOfWeek()->format('d M, Y') : $baseDate->translatedFormat('l, d \d\e F Y') }}</p>
            </div>
            {{-- Period + Nav --}}
            <div class="flex items-center gap-3">
                {{-- Prev --}}
                <a href="{{ route('barber.agenda', ['period'=>$period,'estado'=>$estadoFilter,'offset'=>$dateOffset-($period==='week'?7:1)]) }}"
                   class="h-9 w-9 rounded-xl border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-white hover:border-white/20 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                </a>
                {{-- Period toggle --}}
                <div class="flex h-9 items-center rounded-xl bg-white/5 border border-white/10 p-0.5">
                    <a href="{{ route('barber.agenda', ['period'=>'day','estado'=>$estadoFilter,'offset'=>0]) }}"
                       class="px-4 h-full flex items-center text-[10px] font-black uppercase tracking-widest transition-all rounded-lg {{ $period==='day' ? 'bg-gold text-black' : 'text-muted hover:text-white' }}">Hoy</a>
                    <a href="{{ route('barber.agenda', ['period'=>'week','estado'=>$estadoFilter,'offset'=>0]) }}"
                       class="px-4 h-full flex items-center text-[10px] font-black uppercase tracking-widest transition-all rounded-lg {{ $period==='week' ? 'bg-gold text-black' : 'text-muted hover:text-white' }}">Semana</a>
                </div>
                {{-- Next --}}
                <a href="{{ route('barber.agenda', ['period'=>$period,'estado'=>$estadoFilter,'offset'=>$dateOffset+($period==='week'?7:1)]) }}"
                   class="h-9 w-9 rounded-xl border border-white/10 bg-white/5 flex items-center justify-center text-muted hover:text-white hover:border-white/20 transition-all">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </a>
                @if($dateOffset !== 0)
                    <a href="{{ route('barber.agenda', ['period'=>$period,'estado'=>$estadoFilter,'offset'=>0]) }}"
                       class="px-3 h-9 rounded-xl border border-gold/30 bg-gold/8 flex items-center text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">Hoy</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4" x-data="{ vista: 'lista' }">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ROW ──────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            {{-- Total período --}}
            <div class="rounded-2xl border border-white/8 bg-[#111] p-4 text-center">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1">{{ $period==='day' ? 'Hoy' : 'Semana' }}</p>
                <p class="text-3xl font-black text-white">{{ $stats['total_period'] }}</p>
                <p class="text-[9px] text-muted mt-0.5">citas</p>
            </div>
            {{-- Pendientes --}}
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4 text-center">
                <p class="text-[9px] font-black uppercase tracking-widest text-amber-400/70 mb-1">Pendientes</p>
                <p class="text-3xl font-black text-amber-300">{{ $stats['pending_period'] }}</p>
                <p class="text-[9px] text-amber-400/50 mt-0.5">en espera</p>
            </div>
            {{-- Confirmadas --}}
            <div class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 p-4 text-center">
                <p class="text-[9px] font-black uppercase tracking-widest text-cyan-400/70 mb-1">Confirmadas</p>
                <p class="text-3xl font-black text-cyan-300">{{ $stats['confirmed_period'] }}</p>
                <p class="text-[9px] text-cyan-400/50 mt-0.5">por atender</p>
            </div>
            {{-- En proceso --}}
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-4 text-center">
                <p class="text-[9px] font-black uppercase tracking-widest text-blue-400/70 mb-1">En proceso</p>
                <p class="text-3xl font-black text-blue-300">{{ $stats['in_process_period'] }}</p>
                <p class="text-[9px] text-blue-400/50 mt-0.5">activas</p>
            </div>
            {{-- Completadas --}}
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 text-center">
                <p class="text-[9px] font-black uppercase tracking-widest text-emerald-400/70 mb-1">Completadas</p>
                <p class="text-3xl font-black text-emerald-300">{{ $stats['completed_period'] }}</p>
                <p class="text-[9px] text-emerald-400/50 mt-0.5">listas</p>
            </div>
            {{-- Productividad --}}
            <div class="col-span-2 sm:col-span-3 lg:col-span-1 rounded-2xl border border-gold/20 bg-gold/5 p-4 flex items-center gap-4 lg:flex-col lg:text-center lg:items-center lg:justify-center">
                <div class="relative h-14 w-14 shrink-0">
                    <svg class="h-14 w-14 -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="3"/>
                        <circle cx="18" cy="18" r="15.9" fill="none" stroke="#d4af37" stroke-width="3"
                                stroke-dasharray="{{ $stats['productivity'] }},100"
                                stroke-linecap="round"/>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-xs font-black text-gold">{{ $stats['productivity'] }}%</span>
                </div>
                <div>
                    <p class="text-[9px] font-black uppercase tracking-widest text-gold/70">Productividad</p>
                    <p class="text-[10px] text-gold/50 mt-0.5">{{ $stats['completed_count'] }} completadas en total</p>
                </div>
            </div>
        </section>

        {{-- ── PRÓXIMA CITA ────────────────────────────────── --}}
        {{-- La siguiente cita real es la más próxima aún no atendida: pendiente O confirmada
             ($agenda ya viene ordenada por fecha y hora_inicio) --}}
        @php $nextAppt = $agenda->whereIn('estado', ['pendiente', 'confirmada'])->first(); @endphp
        @if($nextAppt && $estadoFilter === '')
            <section class="ui-card-premium p-0 overflow-hidden border-gold/30" style="box-shadow:0 0 40px rgba(212,175,55,0.12)">
                <div class="flex flex-col sm:flex-row">
                    <div class="bg-gradient-to-br from-gold to-gold-dim p-6 sm:w-60 flex flex-col justify-center items-center text-black">
                        <p class="text-[9px] font-black uppercase tracking-[0.2em] mb-1 opacity-70">Siguiente Cita</p>
                        <p class="text-5xl font-black leading-none">{{ substr($nextAppt->hora_inicio,0,5) }}</p>
                        <p class="text-[9px] font-bold opacity-60 mt-2 uppercase tracking-wider">{{ \Carbon\Carbon::parse($nextAppt->fecha)->translatedFormat('d M') }}</p>
                    </div>
                    <div class="flex-1 p-6 flex flex-col sm:flex-row items-center justify-between gap-6 bg-gradient-to-r from-white/[0.03] to-transparent">
                        <div>
                            <h3 class="text-xl font-black text-white uppercase tracking-tight">{{ $nextAppt->client?->user?->name ?? '—' }}</h3>
                            <p class="text-gold font-bold uppercase tracking-widest text-[11px] mt-1">{{ $nextAppt->service?->nombre ?? '—' }}</p>
                            <div class="mt-3 flex flex-wrap gap-4 text-[10px] font-bold text-muted uppercase tracking-wider">
                                @if($nextAppt->service?->duracion_min)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                        {{ $nextAppt->service->duracion_min }} min
                                    </span>
                                @endif
                                @if($nextAppt->service?->precio)
                                    <span class="flex items-center gap-1.5">
                                        <svg class="h-3 w-3 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/></svg>
                                        ${{ number_format($nextAppt->service->precio, 0) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        @if($nextAppt->estado === 'pendiente')
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('barber.appointments.status', $nextAppt) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="confirmada">
                                    <button type="submit" class="ui-btn px-7 py-3.5 shadow-[0_0_30px_rgba(212,175,55,0.25)]">
                                        Aprobar cita &rarr;
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('barber.appointments.status', $nextAppt) }}"
                                      onsubmit="return confirm('¿Rechazar esta solicitud de cita?')">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="estado" value="cancelada">
                                    <button type="submit" class="px-5 py-3.5 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest text-muted hover:text-red-400 hover:border-red-500/30 transition">
                                        Rechazar
                                    </button>
                                </form>
                            </div>
                        @else
                            <form method="POST" action="{{ route('barber.appointments.status', $nextAppt) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="en_proceso">
                                <button type="submit" class="ui-btn px-8 py-3.5 shadow-[0_0_30px_rgba(212,175,55,0.25)]">
                                    Iniciar Servicio &rarr;
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- ── FILTRO POR ESTADO ───────────────────────────── --}}
        <section class="flex flex-wrap items-center gap-2">
            <span class="text-[9px] font-black uppercase tracking-widest text-muted mr-1">Filtrar:</span>
            @php
                $estadoOpts = [
                    ''           => ['label'=>'Todas',       'cls'=>'border-white/15 text-muted hover:text-white'],
                    'pendiente'  => ['label'=>'Pendiente',   'cls'=>'border-amber-500/30 text-amber-300 bg-amber-500/8'],
                    'confirmada' => ['label'=>'Confirmada',  'cls'=>'border-cyan-500/30 text-cyan-300 bg-cyan-500/8'],
                    'en_proceso' => ['label'=>'En proceso',  'cls'=>'border-blue-500/30 text-blue-300 bg-blue-500/8'],
                    'completada' => ['label'=>'Completada',  'cls'=>'border-emerald-500/30 text-emerald-300 bg-emerald-500/8'],
                    'cancelada'  => ['label'=>'Cancelada',   'cls'=>'border-red-500/30 text-red-400 bg-red-500/8'],
                    'no_asistio' => ['label'=>'No asistió',  'cls'=>'border-white/20 text-white/60 bg-white/5'],
                ];
            @endphp
            @foreach($estadoOpts as $val => $opt)
                <a href="{{ route('barber.agenda', ['period'=>$period,'estado'=>$val,'offset'=>$dateOffset]) }}"
                   class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-[10px] font-black uppercase tracking-widest transition-all
                       {{ $estadoFilter === $val ? 'bg-gold border-gold text-black' : $opt['cls'] }}">
                    {{ $opt['label'] }}
                    @if($val !== '')
                        @php $count = match($val){
                            'pendiente'  => $stats['pending_period'],
                            'confirmada' => $stats['confirmed_period'],
                            'en_proceso' => $stats['in_process_period'],
                            'completada' => $stats['completed_period'],
                            'cancelada'  => $stats['cancelled_period'],
                            'no_asistio' => $stats['no_show_period'],
                            default      => 0,
                        }; @endphp
                        @if($count > 0)
                            <span class="flex h-4 w-4 items-center justify-center rounded-full {{ $estadoFilter === $val ? 'bg-black/20 text-black' : 'bg-white/10 text-white' }} text-[8px] font-black">{{ $count }}</span>
                        @endif
                    @else
                        <span class="flex h-4 w-4 items-center justify-center rounded-full {{ $estadoFilter === '' ? 'bg-black/20 text-black' : 'bg-white/10 text-white' }} text-[8px] font-black">{{ $stats['total_period'] }}</span>
                    @endif
                </a>
            @endforeach
        </section>

        {{-- ── VISTA TIMELINE DEL DÍA ──────────────────────────
             Columna de horas 09:00–21:00 (1px = 1min) con bloques
             proporcionales a la duración: comunica huecos y empalmes de
             un vistazo, cosa que la lista no puede. Solo en período día
             (en semana las citas de días distintos se encimarían). --}}
        @if($period === 'day')
        <section x-show="vista === 'timeline'" x-cloak class="rounded-2xl border border-white/8 bg-[#111] p-5">
            <div class="relative" style="height: 720px;">
                {{-- Líneas y etiquetas de hora --}}
                @for($h = 9; $h <= 21; $h++)
                    <div class="absolute left-0 right-0 border-t border-white/5" style="top: {{ ($h - 9) * 60 }}px;">
                        <span class="absolute -top-2.5 left-0 w-12 text-[9px] font-black text-muted">{{ sprintf('%02d:00', $h) }}</span>
                    </div>
                @endfor
                {{-- Bloques de citas --}}
                @foreach($agenda as $appt)
                    @php
                        $ini = (int) substr($appt->hora_inicio, 0, 2) * 60 + (int) substr($appt->hora_inicio, 3, 2);
                        $dur = max(20, (int) ($appt->service?->duracion_min ?? 30)); // mínimo 20px para legibilidad
                        $top = max(0, $ini - 540); // 540 = 09:00
                        $tlCfg = [
                            'pendiente'  => 'border-amber-500/40 bg-amber-500/15 text-amber-200',
                            'confirmada' => 'border-cyan-500/40 bg-cyan-500/15 text-cyan-200',
                            'en_proceso' => 'border-blue-500/40 bg-blue-500/15 text-blue-200',
                            'completada' => 'border-emerald-500/40 bg-emerald-500/15 text-emerald-200',
                            'cancelada'  => 'border-red-500/30 bg-red-500/10 text-red-300 opacity-50',
                            'no_asistio' => 'border-white/20 bg-white/5 text-white/50 opacity-50',
                        ][$appt->estado] ?? 'border-white/15 bg-white/5 text-white';
                    @endphp
                    <div class="absolute left-14 right-2 rounded-lg border px-3 py-1 overflow-hidden {{ $tlCfg }}"
                         style="top: {{ $top }}px; height: {{ min($dur, 720 - $top) }}px;"
                         title="{{ substr($appt->hora_inicio,0,5) }}–{{ substr($appt->hora_fin,0,5) }} · {{ $appt->client?->user?->name }} · {{ $appt->service?->nombre }}">
                        <p class="text-[10px] font-black truncate leading-tight">
                            {{ substr($appt->hora_inicio, 0, 5) }} · {{ $appt->client?->user?->name ?? '—' }}
                        </p>
                        @if($dur >= 35)
                            <p class="text-[9px] opacity-70 truncate leading-tight">{{ $appt->service?->nombre ?? '—' }}</p>
                        @endif
                    </div>
                @endforeach
                @if($agenda->isEmpty())
                    <p class="absolute inset-0 flex items-center justify-center text-xs font-bold text-muted uppercase tracking-widest">Sin citas para hoy</p>
                @endif
            </div>
        </section>
        @endif

        {{-- ── LISTA DE CITAS ──────────────────────────────── --}}
        <section class="space-y-3">
            <div class="px-1 flex items-center justify-between">
                <p class="text-[11px] font-bold text-muted uppercase tracking-wider">{{ $agenda->count() }} cita{{ $agenda->count() !== 1 ? 's' : '' }}{{ $estadoFilter ? ' · '.ucfirst(str_replace('_',' ',$estadoFilter)) : '' }}</p>
                @if($period === 'day')
                    <div class="flex h-8 items-center rounded-lg bg-white/5 border border-white/10 p-0.5">
                        <button type="button" @click="vista = 'lista'"
                                :class="vista === 'lista' ? 'bg-gold text-black' : 'text-muted hover:text-white'"
                                class="px-3 h-full flex items-center text-[9px] font-black uppercase tracking-widest rounded-md transition-all">Lista</button>
                        <button type="button" @click="vista = 'timeline'"
                                :class="vista === 'timeline' ? 'bg-gold text-black' : 'text-muted hover:text-white'"
                                class="px-3 h-full flex items-center text-[9px] font-black uppercase tracking-widest rounded-md transition-all">Timeline</button>
                    </div>
                @endif
            </div>

            <div class="space-y-3" x-show="vista === 'lista' || {{ $period !== 'day' ? 'true' : 'false' }}">
            @forelse($agenda as $appointment)
                @php
                    $stCfg = [
                        'pendiente'  => ['pill'=>'border-amber-500/30 bg-amber-500/10 text-amber-300',  'dot'=>'bg-amber-400 animate-pulse', 'bar'=>'bg-amber-400'],
                        'confirmada' => ['pill'=>'border-cyan-500/30 bg-cyan-500/10 text-cyan-300',      'dot'=>'bg-cyan-400 animate-pulse',  'bar'=>'bg-cyan-400'],
                        'en_proceso' => ['pill'=>'border-blue-500/30 bg-blue-500/10 text-blue-300',     'dot'=>'bg-blue-400 animate-pulse',  'bar'=>'bg-blue-400'],
                        'completada' => ['pill'=>'border-emerald-500/30 bg-emerald-500/10 text-emerald-300','dot'=>'bg-emerald-400',         'bar'=>'bg-emerald-400'],
                        'cancelada'  => ['pill'=>'border-red-500/30 bg-red-500/10 text-red-400',         'dot'=>'bg-red-400',               'bar'=>'bg-red-400'],
                        'no_asistio' => ['pill'=>'border-white/20 bg-white/10 text-white/60',            'dot'=>'bg-white/40',               'bar'=>'bg-white/30'],
                    ];
                    $sc = $stCfg[$appointment->estado] ?? ['pill'=>'border-white/10 bg-white/5 text-muted','dot'=>'bg-white/30','bar'=>'bg-white/20'];
                @endphp
                <article class="group rounded-2xl border border-white/8 bg-[#111] overflow-hidden hover:border-white/15 transition-all duration-300">
                    {{-- Estado accent bar --}}
                    <div class="h-0.5 w-full {{ $sc['bar'] }} opacity-50"></div>
                    <div class="flex flex-col lg:flex-row">
                        {{-- Time block --}}
                        <div class="flex flex-row lg:flex-col justify-between lg:justify-center items-center lg:w-40 px-5 py-4 lg:py-6 border-b lg:border-b-0 lg:border-r border-white/5 bg-black/20 shrink-0">
                            <div class="lg:text-center">
                                <p class="text-[9px] font-bold text-muted uppercase tracking-wider">{{ \Carbon\Carbon::parse($appointment->fecha)->translatedFormat('d M') }}</p>
                                <p class="text-2xl font-black text-white mt-0.5">{{ substr($appointment->hora_inicio,0,5) }}</p>
                                @if($appointment->service?->duracion_min)
                                    <p class="text-[9px] text-muted mt-1 uppercase tracking-wider">{{ $appointment->service->duracion_min }} min</p>
                                @endif
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $sc['pill'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $sc['dot'] }}"></span>
                                {{ str_replace('_',' ',$appointment->estado) }}
                            </span>
                        </div>

                        {{-- Client + Service --}}
                        <div class="flex-1 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-5">
                            <div class="flex items-center gap-4">
                                <div class="h-11 w-11 rounded-xl bg-gradient-to-br from-gold/15 to-gold/5 border border-gold/15 flex items-center justify-center text-sm font-black text-gold shrink-0">
                                    {{ strtoupper(substr($appointment->client?->user?->name ?? 'C', 0, 2)) }}
                                </div>
                                <div>
                                    <p class="font-black text-white text-base">{{ $appointment->client?->user?->name ?? '—' }}</p>
                                    <p class="text-[11px] font-bold text-gold uppercase tracking-wider mt-0.5">{{ $appointment->service?->nombre ?? '—' }}</p>
                                    @if($appointment->service?->precio)
                                        <p class="text-[10px] text-muted mt-0.5">${{ number_format($appointment->service->precio, 0) }}</p>
                                    @endif
                                </div>
                            </div>

                            {{-- Acción contextual de un toque: el flujo de una cita es lineal
                                 (pendiente → confirmada → en_proceso → completada), así que se
                                 muestra un solo botón grande con el siguiente paso — pensado
                                 para uso en móvil junto a la silla, sin dropdowns. --}}
                            @php
                                $nextAction = match($appointment->estado) {
                                    'pendiente'  => ['estado' => 'confirmada', 'label' => 'Confirmar',        'cls' => 'bg-cyan-500/10 border-cyan-500/30 text-cyan-300 hover:bg-cyan-400 hover:text-black'],
                                    'confirmada' => ['estado' => 'en_proceso', 'label' => 'Iniciar Servicio', 'cls' => 'bg-gold/10 border-gold/25 text-gold hover:bg-gold hover:text-black'],
                                    'en_proceso' => ['estado' => 'completada', 'label' => 'Completar',        'cls' => 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300 hover:bg-emerald-400 hover:text-black'],
                                    default      => null,
                                };
                            @endphp
            @if($nextAction)
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($appointment->estado === 'pendiente')
                                        <form method="POST" action="{{ route('barber.appointments.status', $appointment) }}"
                                              onsubmit="return confirm('¿Rechazar esta solicitud de cita?')">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="estado" value="cancelada">
                                            <button type="submit" class="h-11 px-4 rounded-xl border border-white/10 text-[10px] font-black uppercase tracking-widest text-muted hover:text-red-400 hover:border-red-500/30 transition-all">
                                                Rechazar
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('barber.appointments.status', $appointment) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="estado" value="{{ $nextAction['estado'] }}">
                                        <button type="submit"
                                                class="h-11 px-6 rounded-xl border text-[11px] font-black uppercase tracking-widest transition-all {{ $nextAction['cls'] }}">
                                            {{ $nextAction['label'] }} &rarr;
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-[9px] font-bold text-muted uppercase tracking-wider shrink-0">Sin acciones</span>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-white/10 py-20 text-center">
                    <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin citas para este período</p>
                    @if($estadoFilter)
                        <a href="{{ route('barber.agenda', ['period'=>$period,'offset'=>$dateOffset]) }}"
                           class="mt-3 inline-block text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">Ver todas</a>
                    @endif
                </div>
            @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
