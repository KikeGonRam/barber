<x-app-layout>
    <x-slot name="header">
        @php
            $mode = ($adminMode ?? false) ? 'admin' : (($isBarberMode ?? false) ? 'barber' : (($isReceptionMode ?? false) ? 'reception' : 'client'));
            $modeLabels  = ['admin'=>'Administrativo','barber'=>'Profesional','reception'=>'Operativo','client'=>'Personal'];
            $modeColors  = ['admin'=>'text-gold','barber'=>'text-amber-400','reception'=>'text-indigo-400','client'=>'text-gold'];
        @endphp
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50 mb-1">UrbanBlade · Dashboard</p>
                <h2 class="text-xl font-black text-white uppercase tracking-tight">
                    Panel <span class="{{ $modeColors[$mode] }}">{{ $modeLabels[$mode] }}</span>
                </h2>
                <p class="text-[10px] text-white/50 font-bold mt-0.5 uppercase tracking-wider">{{ now()->translatedFormat('l d \d\e F, Y') }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                @if($adminMode ?? false)
                    <form method="POST" action="{{ route('settings.maintenance.toggle') }}">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl border text-[9px] font-black uppercase tracking-widest transition-all
                                {{ ($maintenanceMode ?? false) ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-white/[0.03] border-white/8 text-white/40 hover:text-white hover:border-white/20' }}">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ ($maintenanceMode ?? false) ? 'Mantenimiento ON' : 'Mantenimiento' }}
                        </button>
                    </form>
                    <a href="{{ route('backups.database.download') }}"
                        class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-emerald-500/25 bg-emerald-500/[0.06] text-emerald-400 text-[9px] font-black uppercase tracking-widest hover:bg-emerald-500/10 transition-all">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Backup
                    </a>
                @endif
                <span class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-white/8 bg-white/[0.03] text-[9px] font-black uppercase tracking-widest text-white/40">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Sistema Activo
                </span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-4">

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- ADMIN DASHBOARD                                           --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    @if($adminMode ?? false)
    @php
        $statusMap = [
            'completada' => ['cls'=>'border-emerald-500/25 bg-emerald-500/10 text-emerald-300','dot'=>'bg-emerald-400','label'=>'Completada'],
            'pendiente'  => ['cls'=>'border-amber-500/25  bg-amber-500/10  text-amber-300', 'dot'=>'bg-amber-400', 'label'=>'Pendiente'],
            'en_proceso' => ['cls'=>'border-blue-500/25   bg-blue-500/10   text-blue-300',  'dot'=>'bg-blue-400',  'label'=>'En proceso'],
            'cancelada'  => ['cls'=>'border-red-500/25    bg-red-500/10    text-red-400',   'dot'=>'bg-red-400',   'label'=>'Cancelada'],
        ];
        $barberStatuses = $kpis['barbers_status'] ?? [];
        $adminSparkline = function(array $values, int $w = 80, int $h = 22): string {
            $vals = array_values(array_filter($values, fn($v) => $v !== null));
            if (count($vals) < 2) return '';
            $max = max($vals) ?: 1; $min = min($vals); $rng = ($max - $min) ?: 1;
            $step = $w / (count($vals) - 1); $pts = [];
            foreach ($vals as $i => $v) {
                $pts[] = round($i * $step, 2).','.round($h - (($v - $min) / $rng) * $h, 2);
            }
            return 'M '.implode(' L ', $pts);
        };
        $incomeSpark = $adminSparkline($incomeChart['values'] ?? []);
    @endphp

    {{-- ── ACCIONES RÁPIDAS ──────────────────────────────────── --}}
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach([
            ['href'=>route('appointments.create'), 'label'=>'Nueva Cita',    'color'=>'blue',   'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['href'=>route('clients.create'),      'label'=>'Nuevo Cliente', 'color'=>'cyan',   'icon'=>'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
            ['href'=>route('payments.create'),     'label'=>'Cobrar',        'color'=>'emerald','icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ['href'=>route('reports.index'),       'label'=>'Reportes',      'color'=>'purple', 'icon'=>'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ] as $a)
            <a href="{{ $a['href'] }}"
               class="group flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-[#111] px-4 py-3.5 hover:border-{{ $a['color'] }}-500/30 hover:bg-{{ $a['color'] }}-500/[0.04] transition-all">
                <div class="h-8 w-8 rounded-xl bg-{{ $a['color'] }}-500/10 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                    <svg class="h-4 w-4 text-{{ $a['color'] }}-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $a['icon'] }}"/></svg>
                </div>
                <span class="text-[11px] font-black text-white uppercase tracking-wide">{{ $a['label'] }}</span>
            </a>
        @endforeach
    </section>

    {{-- ── ZONA 1: RESUMEN ───────────────────────────────────── --}}
    <div class="flex items-center gap-3 px-1 pt-1">
        <span class="text-[10px] font-black uppercase tracking-[0.22em] text-gold">Resumen</span>
        <span class="h-px flex-1 bg-white/[0.06]"></span>
    </div>

    {{-- ── KPIs ──────────────────────────────────────────────── --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        {{-- Citas --}}
        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-blue-500/60 to-transparent"></div>
            <div class="flex items-start justify-between mb-4">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/35">Citas Hoy</p>
                <div class="h-7 w-7 rounded-lg bg-blue-500/10 flex items-center justify-center">
                    <svg class="h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-white leading-none">{{ $kpis['appointments_today'] }}</p>
            <div class="mt-3 flex items-center gap-2 text-[9px] font-black text-white/50">
                <span class="text-blue-400/80">Sem {{ $kpis['appointments_week'] }}</span>
                <span>·</span>
                <span>Mes {{ $kpis['appointments_month'] }}</span>
                @if($kpis['appointment_growth'] != 0)
                    <span class="{{ $kpis['appointment_growth'] >= 0 ? 'text-emerald-400' : 'text-red-400' }} ml-auto">
                        {{ $kpis['appointment_growth'] >= 0 ? '▲' : '▼' }}{{ abs($kpis['appointment_growth']) }}%
                    </span>
                @endif
            </div>
        </div>

        {{-- Ingresos --}}
        <a href="{{ route('payments.index') }}" class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden hover:border-emerald-500/25 transition-all group">
            <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-emerald-500/60 to-transparent"></div>
            <div class="flex items-start justify-between mb-4">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/35">Ingresos Hoy</p>
                <div class="h-7 w-7 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                    <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-emerald-400 leading-none">${{ number_format($kpis['income_today'], 0) }}</p>
            @if($incomeSpark)
                <svg viewBox="0 0 80 22" class="w-full h-5 my-2" preserveAspectRatio="none">
                    <path d="{{ $incomeSpark }}" fill="none" stroke="rgba(52,211,153,0.45)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            @else <div class="h-5 my-2"></div>
            @endif
            <div class="flex items-center gap-2 text-[9px] font-black text-white/50">
                <span class="text-emerald-400/80">Sem ${{ number_format($kpis['income_week'],0) }}</span>
                <span>·</span>
                <span>Mes ${{ number_format($kpis['income_month'],0) }}</span>
                @if($kpis['income_growth'] != 0)
                    <span class="{{ $kpis['income_growth'] >= 0 ? 'text-emerald-400' : 'text-red-400' }} ml-auto">
                        {{ $kpis['income_growth'] >= 0 ? '▲' : '▼' }}{{ abs($kpis['income_growth']) }}%
                    </span>
                @endif
            </div>
        </a>

        {{-- Clientes --}}
        <a href="{{ route('clients.index') }}" class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden hover:border-cyan-500/25 transition-all">
            <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-cyan-500/60 to-transparent"></div>
            <div class="flex items-start justify-between mb-4">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/35">Clientes</p>
                <div class="h-7 w-7 rounded-lg bg-cyan-500/10 flex items-center justify-center">
                    <svg class="h-3.5 w-3.5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-white leading-none">{{ $kpis['active_clients'] }}</p>
            @php $ratio = $kpis['total_clients'] > 0 ? round(($kpis['active_clients']/$kpis['total_clients'])*100) : 0; @endphp
            <div class="mt-3 h-1 w-full bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-cyan-400 rounded-full" style="width:{{ $ratio }}%"></div>
            </div>
            <div class="mt-2 flex items-center gap-2 text-[9px] font-black text-white/50">
                <span class="text-cyan-400/80">{{ $ratio }}% activos</span>
                <span>de {{ $kpis['total_clients'] }} totales</span>
            </div>
        </a>

        {{-- Retención --}}
        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-purple-500/60 to-transparent"></div>
            <div class="flex items-start justify-between mb-4">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/35">Retención</p>
                <div class="h-7 w-7 rounded-lg bg-purple-500/10 flex items-center justify-center">
                    <svg class="h-3.5 w-3.5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                </div>
            </div>
            <p class="text-3xl font-black text-purple-400 leading-none">{{ number_format($kpis['retention_rate'],1) }}<span class="text-lg text-white/40">%</span></p>
            <div class="mt-3 h-1 w-full bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-purple-400 rounded-full" style="width:{{ min(100,$kpis['retention_rate']) }}%"></div>
            </div>
            <div class="mt-2 flex items-center gap-2 text-[9px] font-black text-white/50">
                <span class="text-purple-400/80">{{ $kpis['recurring_clients'] }} recurrentes</span>
                @if(($kpis['low_stock_count'] ?? 0) > 0)
                    <a href="{{ route('inventory.products.index') }}" class="ml-auto flex items-center gap-1 text-amber-400/80 hover:text-amber-400 transition-colors">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        {{ $kpis['low_stock_count'] }} stock bajo
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- ── INSIGHTS DEL ANÁLISIS (Spark / UrbanBlade Analytics) ──
        Hallazgos calculados en vivo sobre la misma BD que analiza el
        proyecto Spark: el conocimiento extraído, donde se decide. --}}
    @if(!empty($insights ?? []))
        <section aria-label="Insights del análisis de datos">
            <div class="flex items-center gap-2 mb-3 px-1">
                <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0013 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <h3 class="text-[11px] font-black uppercase tracking-widest text-gold">Insights del análisis de datos</h3>
                <span class="text-[9px] text-muted">· UrbanBlade Analytics</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($insights as $insight)
                    <article class="rounded-2xl border border-gold/15 bg-gold/[0.03] p-4">
                        <p class="text-[9px] font-black uppercase tracking-widest text-gold/70">{{ $insight['titulo'] }}</p>
                        <p class="text-2xl font-black text-white mt-1">{{ $insight['dato'] }}</p>
                        <p class="text-[11px] text-muted mt-1.5 leading-snug">{{ $insight['detalle'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ── ZONA 2: OPERACIÓN DE HOY ──────────────────────────── --}}
    <div class="flex items-center gap-3 px-1 pt-2">
        <span class="text-[10px] font-black uppercase tracking-[0.22em] text-gold">Operación de hoy</span>
        <span class="h-px flex-1 bg-white/[0.06]"></span>
    </div>

    {{-- ── CUERPO PRINCIPAL ──────────────────────────────────── --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Agenda de hoy --}}
        <div class="lg:col-span-7 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Agenda</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Citas de Hoy</h3>
                </div>
                <a href="{{ route('appointments.index') }}" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-gold transition-colors">
                    Ver todo <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            @if($todayAppointments->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 border border-dashed border-white/[0.06] rounded-xl">
                    <svg class="h-8 w-8 text-white/10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-xs font-bold text-white/45 uppercase tracking-widest">Sin citas hoy</p>
                    <a href="{{ route('appointments.create') }}" class="mt-3 text-[9px] font-black uppercase tracking-widest text-gold/60 hover:text-gold transition-colors">+ Crear cita</a>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($todayAppointments as $appt)
                        @php $st = $statusMap[$appt->estado] ?? ['cls'=>'border-white/10 bg-white/5 text-white/40','dot'=>'bg-white/30','label'=>'—']; @endphp
                        <div class="flex items-center gap-3 p-3 rounded-xl border border-white/[0.05] hover:border-white/10 hover:bg-white/[0.02] transition-all">
                            <div class="w-10 text-center shrink-0">
                                <p class="text-[11px] font-black text-white">{{ substr($appt->hora_inicio ?? '--:--',0,5) }}</p>
                                <p class="text-[8px] text-white/45 font-bold">{{ substr($appt->hora_fin ?? '',0,5) }}</p>
                            </div>
                            <div class="w-px h-7 bg-white/[0.06] shrink-0"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-black text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                                <p class="text-[9px] text-white/35 font-bold truncate">{{ $appt->service?->nombre ?? '—' }} · {{ $appt->barber?->user?->name ?? '—' }}</p>
                            </div>
                            <span class="shrink-0 flex items-center gap-1 text-[8px] font-black uppercase tracking-wider border rounded-full px-2 py-0.5 {{ $st['cls'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $st['dot'] }}"></span>
                                {{ $st['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Panel derecho con tabs --}}
        <div class="lg:col-span-5" x-data="{ tab: 'activity' }">
            <div class="rounded-2xl border border-white/[0.06] bg-[#111] overflow-hidden h-full flex flex-col">

                {{-- Tab headers --}}
                <div class="flex border-b border-white/[0.06]">
                    @foreach([['id'=>'activity','label'=>'Actividad'],['id'=>'stations','label'=>'Estaciones'],['id'=>'topbarber','label'=>'Top Mes']] as $tab)
                        <button type="button" @click="tab='{{ $tab['id'] }}'"
                            class="flex-1 py-3 text-[9px] font-black uppercase tracking-[0.2em] transition-all"
                            :class="tab==='{{ $tab['id'] }}' ? 'text-gold border-b-2 border-gold -mb-px' : 'text-white/50 hover:text-white/60'">
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="flex-1 p-5">

                    {{-- Actividad reciente --}}
                    <div x-show="tab==='activity'" x-transition>
                        <div class="space-y-3">
                            @forelse($recentAppointments->take(6) as $appt)
                                @php $st = $statusMap[$appt->estado] ?? ['cls'=>'border-white/10 bg-white/5 text-white/40','dot'=>'bg-white/30','label'=>'—']; @endphp
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-lg bg-white/[0.04] border border-white/[0.06] flex items-center justify-center text-[10px] font-black text-gold shrink-0">
                                        {{ strtoupper(substr($appt->barber?->user?->name ?? 'B',0,1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[11px] font-bold text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                                        <p class="text-[9px] text-white/50 truncate">{{ \Carbon\Carbon::parse($appt->fecha)->format('d M') }} · {{ substr($appt->hora_inicio,0,5) }}</p>
                                    </div>
                                    <span class="shrink-0 text-[8px] font-black uppercase border rounded-full px-2 py-0.5 {{ $st['cls'] }}">{{ $st['label'] }}</span>
                                </div>
                            @empty
                                <p class="text-xs text-white/45 italic text-center py-8">Sin actividad reciente</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Estaciones en vivo --}}
                    <div x-show="tab==='stations'" x-transition>
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Ocupación en tiempo real</p>
                            <span class="flex items-center gap-1 text-[8px] font-black uppercase text-emerald-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>Live
                            </span>
                        </div>
                        @if(!empty($barberStatuses))
                            <div class="grid grid-cols-2 gap-3">
                                @foreach($barberStatuses as $st)
                                    <div class="rounded-xl border {{ $st['is_busy'] ? 'border-red-500/20 bg-red-500/[0.04]' : 'border-emerald-500/15 bg-emerald-500/[0.04]' }} p-3 text-center">
                                        <div class="relative inline-flex mb-2">
                                            <div class="h-9 w-9 rounded-lg bg-white/[0.05] border border-white/[0.08] flex items-center justify-center text-[11px] font-black text-gold">
                                                {{ strtoupper(substr($st['name'],0,2)) }}
                                            </div>
                                            <span class="absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-full border-2 border-[#111] {{ $st['is_busy'] ? 'bg-red-500' : 'bg-emerald-500' }}"></span>
                                        </div>
                                        <p class="text-[10px] font-black text-white truncate">{{ explode(' ',$st['name'])[0] }}</p>
                                        <p class="text-[8px] font-black uppercase {{ $st['is_busy'] ? 'text-red-400' : 'text-emerald-400' }}">{{ $st['is_busy'] ? 'Ocupado' : 'Libre' }}</p>
                                        @if($st['is_busy'])
                                            <div class="mt-1.5 h-0.5 w-full bg-white/5 rounded-full overflow-hidden">
                                                <div class="h-full bg-gold rounded-full" style="width:{{ $st['progress'] }}%"></div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-white/45 italic text-center py-8">Sin barberos activos</p>
                        @endif
                    </div>

                    {{-- Top barbero del mes --}}
                    <div x-show="tab==='topbarber'" x-transition>
                        @if($kpis['top_barber_name'])
                            <div class="flex flex-col items-center text-center py-4">
                                <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-gold/20 to-gold/5 border border-gold/20 flex items-center justify-center text-2xl font-black text-gold mb-3">
                                    {{ strtoupper(substr($kpis['top_barber_name'],0,2)) }}
                                </div>
                                <p class="text-base font-black text-white uppercase">{{ $kpis['top_barber_name'] }}</p>
                                <p class="text-[9px] text-white/50 font-bold uppercase tracking-widest mt-0.5">Mejor del mes</p>
                                <div class="mt-3 inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gold/15 bg-gold/[0.06]">
                                    <svg class="h-3.5 w-3.5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-sm font-black text-gold">{{ $kpis['top_barber_total'] }}</p>
                                    <p class="text-[9px] text-white/50 font-bold uppercase tracking-wider">citas</p>
                                </div>
                                <div class="flex gap-0.5 mt-3">
                                    @for($s=0;$s<5;$s++)<svg class="h-3.5 w-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-white/45 italic text-center py-8">Sin datos este mes</p>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- ── ZONA 3: ANALÍTICA AVANZADA (plegable, recuerda en localStorage) ──
        Se pliega con max-height (NO display:none) para que los canvas de
        Chart.js conserven su ancho real y no se inicialicen a 0x0. --}}
    <section class="space-y-5" x-data="{
            open: localStorage.getItem('adminAnalytics') === 'true',
            booted: false,
            boot() { if (this.open && !this.booted && window.__ubInitAdminCharts) { this.booted = true; this.$nextTick(() => window.__ubInitAdminCharts()); } },
            toggle() { this.open = !this.open; localStorage.setItem('adminAnalytics', this.open); this.boot(); },
        }" x-init="boot()">
        <button type="button" @click="toggle()"
            class="w-full flex items-center gap-3 rounded-2xl border border-white/[0.06] bg-[#111] px-5 py-4 hover:border-white/12 transition-all"
            :aria-expanded="open.toString()">
            <svg class="h-4 w-4 text-gold shrink-0 transition-transform duration-200" :class="open ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            <div class="text-left">
                <p class="text-[11px] font-black uppercase tracking-widest text-white">Analítica avanzada</p>
                <p class="text-[9px] text-white/45 font-bold">4 gráficas · predicciones IA · telemetría chatbot</p>
            </div>
            <span class="ml-auto text-[9px] font-black uppercase tracking-widest text-gold/70" x-text="open ? 'Ocultar' : 'Ver'"></span>
        </button>

        <div x-show="open" style="display:none" class="space-y-5">

    {{-- ── GRÁFICAS ──────────────────────────────────────────── --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">

        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Últimas 8 semanas</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Tendencia de Ingresos</h3>
                </div>
                <div class="h-2 w-2 rounded-full bg-emerald-400"></div>
            </div>
            @if(!empty(array_filter($incomeChart['values'] ?? [])))
                <div class="h-52"><canvas id="incomeChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin ingresos aún</p>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Distribución</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Demanda de Servicios</h3>
                </div>
                <div class="h-2 w-2 rounded-full bg-gold"></div>
            </div>
            @if(!empty(array_filter($servicesChart['values'] ?? [])))
                <div class="h-52"><canvas id="servicesChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin servicios registrados</p>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Este mes</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Desempeño Barberos</h3>
                </div>
                <div class="flex gap-3 text-[8px] font-black uppercase text-white/45">
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-blue-500"></span>Citas</span>
                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span>Ingresos</span>
                </div>
            </div>
            @if(!empty(array_filter($barberPerformance['appointments'] ?? [])))
                <div class="h-52"><canvas id="barberPerformanceChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin datos de desempeño</p>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Mes actual</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Tendencia de Clientes</h3>
                </div>
                <div class="h-2 w-2 rounded-full bg-purple-400"></div>
            </div>
            @if(!empty(array_filter($clientTrends['values'] ?? [])))
                <div class="h-52"><canvas id="clientTrendsChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin datos de tendencias</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ── FILA INFERIOR: IA + CHATBOT ──────────────────────── --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Predicciones IA --}}
        <div class="lg:col-span-5 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Próximos 7 días</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5 flex items-center gap-2">
                        Predicciones IA
                        <span class="text-[8px] font-black uppercase tracking-widest border border-indigo-500/30 bg-indigo-500/10 text-indigo-400 px-1.5 py-0.5 rounded-full">Beta</span>
                    </h3>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mb-5">
                @foreach([
                    ['id'=>'income-forecast',      'label'=>'Ingresos Est.',  'color'=>'emerald'],
                    ['id'=>'appointment-forecast', 'label'=>'Citas Est.',     'color'=>'blue'],
                    ['id'=>'ai-confidence',        'label'=>'Confianza',      'color'=>'indigo'],
                ] as $ai)
                    <div class="rounded-xl border border-white/[0.05] bg-white/[0.02] p-3 text-center">
                        <p class="text-[8px] font-black uppercase tracking-wider text-white/50 mb-2">{{ $ai['label'] }}</p>
                        <p id="{{ $ai['id'] }}" class="text-lg font-black text-{{ $ai['color'] }}-400">
                            <span class="inline-block w-8 h-1 bg-{{ $ai['color'] }}-500/25 rounded animate-pulse"></span>
                        </p>
                    </div>
                @endforeach
            </div>

            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-white/45 mb-3">Insights</p>
                <div id="ai-insights" class="space-y-2">
                    <div class="flex items-start gap-2 p-3 rounded-xl border border-white/[0.05] bg-white/[0.02] animate-pulse">
                        <div class="h-1.5 w-1.5 rounded-full bg-amber-400 mt-1.5 shrink-0"></div>
                        <p class="text-[10px] text-white/45">Cargando análisis...</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Telemetría Chatbot --}}
        <div class="lg:col-span-7 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Últimos {{ $chatbotTelemetry['window_days'] ?? 7 }} días</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5 flex items-center gap-2">
                        Telemetría Chatbot
                        <span class="flex items-center gap-1 text-[8px] font-black uppercase text-emerald-400">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>OK
                        </span>
                    </h3>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                @foreach([
                    ['label'=>'Eventos',        'val'=> $chatbotTelemetry['total_requests'] ?? 0,                          'color'=>'blue'],
                    ['label'=>'Error Rate',     'val'=> number_format($chatbotTelemetry['error_rate_pct'] ?? 0,1).'%',     'color'=>'red'],
                    ['label'=>'Latencia Prom.', 'val'=> ($chatbotTelemetry['avg_latency_ms'] ?? 0).'ms',                   'color'=>'sky'],
                    ['label'=>'Costo Est.',     'val'=> '$'.number_format($chatbotTelemetry['estimated_cost_usd'] ?? 0,4), 'color'=>'emerald'],
                ] as $tel)
                    <div class="rounded-xl border border-white/[0.05] bg-white/[0.02] p-3">
                        <p class="text-[8px] font-black uppercase tracking-wider text-white/50 mb-1.5">{{ $tel['label'] }}</p>
                        <p class="text-lg font-black text-{{ $tel['color'] }}-400">{{ $tel['val'] }}</p>
                    </div>
                @endforeach
            </div>

            @if(!empty($chatbotTelemetry['top_sources']))
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] text-white/45 mb-3">Top Fuentes</p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        @foreach($chatbotTelemetry['top_sources'] as $source => $count)
                            <div class="rounded-xl border border-white/[0.05] bg-white/[0.02] px-3 py-2 flex items-center justify-between">
                                <span class="text-[9px] font-bold text-white uppercase truncate">{{ str_replace('_',' ',$source) }}</span>
                                <span class="text-[9px] font-black text-gold ml-2 shrink-0">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>
        </div>{{-- /contenedor plegable --}}
    </section>{{-- /zona 3 analítica avanzada --}}

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- BARBER DASHBOARD                                          --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    @elseif($isBarberMode ?? false)

    @php
        $barberToday   = $barberToday   ?? collect();
        $barberPending = $barberPending ?? collect();
        $pendCount     = $barberPending->count();
        $bStatus = [
            'completada' => ['border-emerald-500/25 bg-emerald-500/10 text-emerald-300','Completada'],
            'pendiente'  => ['border-amber-500/25 bg-amber-500/10 text-amber-300','Pendiente'],
            'en_proceso' => ['border-blue-500/25 bg-blue-500/10 text-blue-300','En proceso'],
            'confirmada' => ['border-gold/25 bg-gold/10 text-gold','Confirmada'],
            'cancelada'  => ['border-red-500/25 bg-red-500/10 text-red-400','Cancelada'],
            'no_asistio' => ['border-white/10 bg-white/5 text-white/40','No asistió'],
        ];
        $bNext = $barberToday->first(fn ($a) => in_array($a->estado, ['confirmada','en_proceso','pendiente'], true));
    @endphp

    {{-- Bienvenida --}}
    <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-6 relative overflow-hidden">
        <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gold/5 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row items-center gap-6">
            <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-black shrink-0">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50">Bienvenido de vuelta</p>
                <h3 class="text-xl font-black text-white uppercase mt-0.5">Maestro <span class="text-gold">{{ explode(' ', auth()->user()->name)[0] }}</span></h3>
                <p class="text-xs text-white/40 mt-1">Tienes <strong class="text-white">{{ $kpis['appointments_today'] }}</strong> servicio{{ $kpis['appointments_today'] !== 1 ? 's' : '' }} hoy{!! $pendCount > 0 ? ' · <strong class="text-amber-300">'.$pendCount.'</strong> por aprobar' : '' !!}.</p>
            </div>
            <div class="sm:ml-auto flex gap-3">
                <a href="{{ route('barber.agenda') }}" class="ui-btn px-6 py-3 text-[10px]">Mi Agenda</a>
                <a href="{{ route('barber.profile.edit') }}" class="flex items-center gap-2 px-6 py-3 rounded-xl border border-white/10 bg-white/[0.03] text-[10px] font-black uppercase tracking-widest text-white/60 hover:text-white hover:border-white/20 transition-all">Mi Perfil</a>
            </div>
        </div>
    </section>

    {{-- KPIs (5) --}}
    <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach([
            ['label'=>'Citas Hoy',    'val'=>$kpis['appointments_today'],                    'color'=>'gold'],
            ['label'=>'Por Aprobar',  'val'=>$pendCount,                                     'color'=>'amber'],
            ['label'=>'Ingresos Mes', 'val'=>'$'.number_format($kpis['income_month'],0),     'color'=>'emerald'],
            ['label'=>'Propinas Mes', 'val'=>'$'.number_format($kpis['tips_month'] ?? 0,0),  'color'=>'gold'],
            ['label'=>'Rating',       'val'=>$kpis['rating'],                                'color'=>'white'],
        ] as $kpi)
            <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 text-center">
                <p class="text-[9px] font-black uppercase tracking-[0.22em] text-white/50 mb-3">{{ $kpi['label'] }}</p>
                <p class="text-2xl font-black text-{{ $kpi['color'] === 'gold' ? 'gold' : ($kpi['color'] === 'emerald' ? 'emerald-400' : ($kpi['color'] === 'amber' ? 'amber-300' : 'white')) }}">{{ $kpi['val'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Por aprobar (solo si hay solicitudes pendientes) --}}
    @if($pendCount > 0)
        <section class="rounded-2xl border border-amber-500/25 bg-amber-500/[0.04] p-5">
            <div class="flex items-center gap-2 mb-4">
                <svg class="h-4 w-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h3 class="text-[11px] font-black uppercase tracking-widest text-amber-300">Esperando tu aprobación</h3>
                <span class="ml-auto text-[9px] font-black text-amber-300/70">{{ $pendCount }} solicitud{{ $pendCount !== 1 ? 'es' : '' }}</span>
            </div>
            <div class="space-y-2">
                @foreach($barberPending as $appt)
                    <div class="flex flex-wrap items-center gap-3 p-3 rounded-xl border border-amber-500/10 bg-black/20">
                        <div class="w-14 text-center shrink-0">
                            <p class="text-[11px] font-black text-white">{{ substr($appt->hora_inicio ?? '--:--',0,5) }}</p>
                            <p class="text-[8px] text-white/45 font-bold">{{ \Carbon\Carbon::parse($appt->fecha)->format('d M') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-black text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                            <p class="text-[9px] text-white/40 font-bold truncate">{{ $appt->service?->nombre ?? '—' }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('barber.appointments.status', $appt) }}">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="confirmada">
                                <button type="submit" class="ui-btn px-4 py-2 text-[9px] tracking-widest">Aprobar</button>
                            </form>
                            <form method="POST" action="{{ route('barber.appointments.status', $appt) }}" onsubmit="return confirm('¿Rechazar esta solicitud de cita?')">
                                @csrf @method('PATCH')
                                <input type="hidden" name="estado" value="cancelada">
                                <button type="submit" class="text-[9px] font-black uppercase tracking-widest text-white/40 hover:text-red-400 transition px-2">Rechazar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Citas de hoy --}}
    <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Agenda</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Citas de Hoy</h3>
            </div>
            <a href="{{ route('barber.agenda') }}" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-gold transition-colors">
                Ver agenda <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        @if($barberToday->isEmpty())
            <div class="flex flex-col items-center justify-center py-12 border border-dashed border-white/[0.06] rounded-xl">
                <svg class="h-8 w-8 text-white/10 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-xs font-bold text-white/45 uppercase tracking-widest">Sin citas hoy</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($barberToday as $appt)
                    @php $sc = $bStatus[$appt->estado] ?? ['border-white/10 bg-white/5 text-white/40','—']; $isNext = $bNext && (string)$bNext->id === (string)$appt->id; @endphp
                    <div class="flex items-center gap-3 p-3 rounded-xl border transition-all {{ $isNext ? 'border-gold/30 bg-gold/[0.04]' : 'border-white/[0.05] hover:border-white/10' }}">
                        <div class="w-12 text-center shrink-0">
                            <p class="text-[11px] font-black {{ $isNext ? 'text-gold' : 'text-white' }}">{{ substr($appt->hora_inicio ?? '--:--',0,5) }}</p>
                            <p class="text-[8px] text-white/45 font-bold">{{ substr($appt->hora_fin ?? '',0,5) }}</p>
                        </div>
                        <div class="w-px h-7 bg-white/[0.06] shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-black text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}
                                @if($isNext)<span class="ml-1 text-[8px] font-black uppercase tracking-wider text-gold">· Siguiente</span>@endif
                            </p>
                            <p class="text-[9px] text-white/35 font-bold truncate">{{ $appt->service?->nombre ?? '—' }}</p>
                        </div>
                        <span class="shrink-0 text-[8px] font-black uppercase tracking-wider border rounded-full px-2 py-0.5 {{ $sc[0] }}">{{ $sc[1] }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Gráficas --}}
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="mb-5">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Últimos 7 días</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Productividad Semanal</h3>
            </div>
            @if(!empty(array_filter($performanceChart['values'] ?? [])))
                <div class="h-52"><canvas id="performanceChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin datos suficientes</p>
                </div>
            @endif
        </div>
        <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="mb-5">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Último año</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Top Especialidades</h3>
            </div>
            @if(!empty(array_filter($servicesChart['values'] ?? [])))
                <div class="h-52"><canvas id="servicesChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin especialidades aún</p>
                </div>
            @endif
        </div>
    </section>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- RECEPTIONIST DASHBOARD                                    --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    @elseif($isReceptionMode ?? false)

    @php $pendingOrdersList = $pending_orders_list ?? collect(); @endphp

    {{-- Bienvenida --}}
    <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 h-32 w-32 rounded-full bg-indigo-500/5 blur-2xl"></div>
        <div class="relative flex flex-col sm:flex-row sm:items-center gap-4">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50">Recepción</p>
                <h3 class="text-base font-black text-white uppercase mt-0.5">Hola, <span class="text-indigo-400">{{ explode(' ',auth()->user()->name)[0] }}</span></h3>
                <p class="text-[10px] text-white/50 mt-1">Centro de mando activo.</p>
            </div>
            <div class="sm:ml-auto flex flex-wrap gap-2">
                <a href="{{ route('appointments.create') }}" class="ui-btn px-4 py-2 text-[9px]">+ Cita (walk-in)</a>
                <a href="{{ route('payments.create') }}" class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-white/10 bg-white/[0.03] text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-white transition-all">Cobrar</a>
                <a href="{{ route('orders.index') }}" class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-white/10 bg-white/[0.03] text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-white transition-all">Pedidos</a>
            </div>
        </div>
    </section>

    {{-- KPIs (6) --}}
    <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach([
            ['label'=>'Citas Hoy',        'val'=>$kpis['appointments_today'],                       'color'=>'indigo',  'href'=>route('appointments.index')],
            ['label'=>'Cobrado Hoy',      'val'=>'$'.number_format($kpis['collected_today'] ?? 0,0),'color'=>'emerald', 'href'=>route('payments.index')],
            ['label'=>'Cobros Pend.',     'val'=>$kpis['pending_payments'],                         'color'=>'amber',   'href'=>route('payments.create')],
            ['label'=>'Pedidos',          'val'=>$kpis['pending_orders'] ?? 0,                      'color'=>'cyan',    'href'=>route('orders.index')],
            ['label'=>'Nuevos Clientes',  'val'=>$kpis['new_clients_today'],                        'color'=>'indigo',  'href'=>route('clients.index')],
            ['label'=>'Stock Crítico',    'val'=>$kpis['low_stock_count'],                          'color'=>'red',     'href'=>route('inventory.products.index')],
        ] as $kpi)
            <a href="{{ $kpi['href'] }}" class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 relative overflow-hidden hover:border-{{ $kpi['color'] }}-500/25 transition-all group">
                <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-{{ $kpi['color'] }}-500/60 to-transparent"></div>
                <p class="text-[9px] font-black uppercase tracking-[0.2em] text-white/50 mb-3">{{ $kpi['label'] }}</p>
                <p class="text-2xl font-black text-{{ $kpi['color'] }}-400">{{ $kpi['val'] }}</p>
            </a>
        @endforeach
    </section>

    {{-- Próximas llegadas + Flujo --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Hoy</p>
                    <h3 class="text-sm font-black text-white uppercase mt-0.5">Próximas Llegadas</h3>
                </div>
                <a href="{{ route('appointments.index') }}" class="text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-gold transition-colors">Agenda completa →</a>
            </div>
            <div class="space-y-2">
                @forelse($nextAppointments as $appt)
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-white/[0.05] hover:border-indigo-500/20 hover:bg-indigo-500/[0.03] transition-all">
                        <div class="h-9 w-9 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-black text-xs shrink-0">
                            {{ substr($appt->hora_inicio,0,2) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-black text-white truncate uppercase">{{ $appt->client?->user?->name }}</p>
                            <p class="text-[9px] text-white/50 truncate">{{ $appt->service?->nombre }} · {{ $appt->barber?->user?->name }}</p>
                        </div>
                        <p class="text-xs font-black text-white shrink-0">{{ substr($appt->hora_inicio,0,5) }}</p>
                    </div>
                @empty
                    <div class="py-12 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                        <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin llegadas próximas</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="lg:col-span-5 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="mb-5">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Distribución horaria</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Flujo Operativo</h3>
            </div>
            @if(!empty(array_filter($flow_chart['values'] ?? [])))
                <div class="h-52"><canvas id="flowChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin flujo registrado hoy</p>
                </div>
            @endif
        </div>
    </section>

    {{-- Pedidos por entregar --}}
    <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
        <div class="flex items-center justify-between mb-5">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Tienda</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Pedidos por Entregar</h3>
            </div>
            <a href="{{ route('orders.index') }}" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-white/50 hover:text-gold transition-colors">
                Ir a la bandeja <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        @forelse($pendingOrdersList as $order)
            <a href="{{ route('orders.index') }}" class="flex items-center gap-3 p-3 rounded-xl border border-white/[0.05] hover:border-cyan-500/20 hover:bg-cyan-500/[0.03] transition-all mb-2 last:mb-0">
                <div class="h-9 w-9 rounded-lg bg-cyan-500/10 flex items-center justify-center text-cyan-400 shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-black text-white truncate">{{ $order->folio }} · {{ $order->client?->user?->name ?? 'Cliente' }}</p>
                    <p class="text-[9px] text-white/45 font-bold truncate">{{ optional($order->created_at)->translatedFormat('d M, H:i') }} · {{ count($order->items ?? []) }} artículo{{ count($order->items ?? []) !== 1 ? 's' : '' }}</p>
                </div>
                <p class="text-sm font-black text-gold shrink-0">${{ number_format($order->total, 2) }}</p>
            </a>
        @empty
            <div class="py-12 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin pedidos pendientes</p>
            </div>
        @endforelse
    </section>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- CLIENT DASHBOARD                                          --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    @elseif($isClientMode ?? false)
    @php
        $loy      = $loyalty ?? [];
        $lvl      = $loy['nivel'] ?? 'nuevo';
        $pts      = $loy['puntos'] ?? 0;
        $disc     = $loy['discount_pct'] ?? 0;
        $faltan   = $loy['citas_faltan'] ?? 0;
        $nextLvl  = $loy['next_nivel'] ?? null;
        $progPct  = $loy['progress_pct'] ?? 0;
        $lvlLabels= ['nuevo'=>'Caballero','regular'=>'Regular','vip'=>'V.I.P','leyenda'=>'Leyenda'];
        $lvlLabel = $lvlLabels[$lvl] ?? strtoupper($lvl);
        $nextLabel= $nextLvl ? ($lvlLabels[$nextLvl] ?? '') : null;
        $wonRaffle= $loy['won_raffle'] ?? null;
        $recentTx = $loy['recent_transactions'] ?? collect();
        $lvlColor = ['nuevo'=>'rgba(255,255,255,0.5)','regular'=>'#60a5fa','vip'=>'#d4af37','leyenda'=>'#e879f9'][$lvl] ?? '#d4af37';
        // Datos de la tarjeta de membresia (numero, alta, QR).
        $memberCard   = app(\App\Services\Member\MemberCardService::class);
        $memberNumber = $memberCard->memberNumber(auth()->user());
        $memberSince  = $memberCard->memberSince(auth()->user());
        $memberQr     = $memberCard->qrDataUri(auth()->user());
    @endphp

    {{-- Bienvenida --}}
    <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-6 relative overflow-hidden">
        <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-gold/5 blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col sm:flex-row items-center gap-6">
            <div class="h-16 w-16 rounded-2xl bg-gradient-to-br from-gold to-amber-600 flex items-center justify-center text-black shrink-0">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50">Bienvenido</p>
                <h3 class="text-xl font-black text-white uppercase mt-0.5"><span class="text-gold">{{ explode(' ',auth()->user()->name)[0] }}</span></h3>
                <p class="text-xs text-white/40 mt-1">Estatus actual: <span class="text-white font-black uppercase">{{ $kpis['membership_status'] }}</span></p>
            </div>
            <div class="sm:ml-auto flex gap-3">
                <a href="{{ route('client.appointments.create') }}" class="ui-btn px-6 py-3 text-[10px]">Reservar Cita</a>
                <a href="{{ route('client.appointments.index') }}" class="flex items-center gap-2 px-6 py-3 rounded-xl border border-white/10 bg-white/[0.03] text-[10px] font-black uppercase tracking-widest text-white/50 hover:text-white transition-all">Mis Citas</a>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach([
            ['label'=>'Visitas Totales',   'val'=>$kpis['total_appointments'],     'color'=>'gold'],
            ['label'=>'Completadas',        'val'=>$kpis['completed_appointments'], 'color'=>'white'],
            ['label'=>'Barbero Favorito',  'val'=>$kpis['favorite_barber'],         'color'=>'white', 'sm'=>true],
            ['label'=>'Puntos Acumulados', 'val'=>$kpis['completed_appointments']*10,'color'=>'gold'],
        ] as $kpi)
            <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5 text-center hover:border-gold/20 transition-all">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-gold/60 mb-3">{{ $kpi['label'] }}</p>
                <p class="font-black {{ ($kpi['sm'] ?? false) ? 'text-base' : 'text-2xl' }} text-{{ $kpi['color'] === 'gold' ? 'gold' : 'white' }} truncate px-1">{{ $kpi['val'] }}</p>
            </div>
        @endforeach
    </section>

    {{-- Próxima cita spotlight --}}
    @if($nextAppointment)
        <section class="rounded-2xl border border-gold/25 overflow-hidden" style="background:#0d0d0d;">
            <div class="flex flex-col md:flex-row">
                <div class="bg-gradient-to-br from-gold to-amber-600 p-6 md:w-56 flex flex-col justify-center items-center text-black shrink-0">
                    <p class="text-[9px] font-black uppercase tracking-[0.2em] opacity-60 mb-1">Tu Próxima Cita</p>
                    <p class="text-3xl font-black leading-none">{{ \Carbon\Carbon::parse($nextAppointment['fecha'])->format('d') }}</p>
                    <p class="text-sm font-bold opacity-80">{{ \Carbon\Carbon::parse($nextAppointment['fecha'])->translatedFormat('M Y') }}</p>
                    <p class="text-xl font-black mt-2">{{ substr($nextAppointment['hora_inicio'],0,5) }}</p>
                </div>
                <div class="p-6 flex-1 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50 mb-1">Servicio</p>
                        <h3 class="text-lg font-black text-white uppercase">{{ $nextAppointment['service']['nombre'] ?? 'Servicio' }}</h3>
                        <p class="text-xs text-gold/70 font-bold uppercase tracking-widest mt-0.5">Con Maestro {{ $nextAppointment['barber']['user']['name'] ?? '—' }}</p>
                    </div>
                    <a href="{{ route('client.appointments.index') }}" class="shrink-0 flex items-center gap-2 px-6 py-3 rounded-xl border border-white/10 bg-white/[0.05] text-[10px] font-black uppercase tracking-widest text-white hover:border-gold/30 hover:bg-gold/[0.06] transition-all">
                        Ver detalles →
                    </a>
                </div>
            </div>
        </section>
    @else
        <section class="rounded-2xl border border-dashed border-gold/15 bg-gold/[0.02] p-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>
                <p class="text-sm font-black text-white uppercase">Sin citas próximas</p>
                <p class="text-xs text-white/50 mt-0.5">Reserva tu siguiente visita y mantén tu estilo impecable.</p>
            </div>
            <a href="{{ route('client.appointments.create') }}" class="ui-btn px-8 py-3 shrink-0">Reservar ahora →</a>
        </section>
    @endif

    {{-- Gráfica + Lealtad --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        {{-- Gráfica de visitas --}}
        <div class="lg:col-span-7 rounded-2xl border border-white/[0.06] bg-[#111] p-5">
            <div class="mb-5">
                <p class="text-[9px] font-black uppercase tracking-[0.25em] text-white/50">Últimos 6 meses</p>
                <h3 class="text-sm font-black text-white uppercase mt-0.5">Frecuencia de Visitas</h3>
            </div>
            @if(!empty(array_filter($visit_chart['values'] ?? [])))
                <div class="h-52"><canvas id="visitChart"></canvas></div>
            @else
                <div class="h-52 flex items-center justify-center border border-dashed border-white/[0.06] rounded-xl">
                    <p class="text-xs text-white/45 uppercase tracking-widest font-bold">Sin historial aún</p>
                </div>
            @endif
        </div>

        {{-- Panel de lealtad --}}
        <div class="lg:col-span-5 space-y-4">

            {{-- Tarjeta de membresia --}}
            <x-membership-card
                :nivel="$lvl"
                :label="$lvlLabel"
                :puntos="$pts"
                :nombre="auth()->user()->name"
                :numero="$memberNumber"
                :desde="$memberSince"
                :qr="$memberQr" />

            {{-- Progreso + beneficios + movimientos --}}
            <div class="rounded-2xl border border-white/[0.06] bg-[#0d0d0d] p-5 space-y-4">

                {{-- Progreso --}}
                @if($nextLvl)
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-[8px] font-black uppercase tracking-[0.2em] text-white/45">Próximo: <span style="color:#d4af37;">{{ $nextLabel }}</span></p>
                            <p class="text-[9px] font-black text-gold">{{ $faltan > 0 ? "Faltan {$faltan} cita".($faltan!==1?'s':'') : '¡Listo!' }}</p>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-white/[0.05] overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ round($progPct) }}%;background:linear-gradient(90deg,#d4af37,#f5d87a);box-shadow:0 0 6px rgba(212,175,55,0.35);transition:width 1s;"></div>
                        </div>
                    </div>
                @else
                    <div class="flex items-center gap-2 py-1">
                        <svg class="h-3 w-3" style="fill:rgba(232,121,249,0.7);" viewBox="0 0 24 24"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm2 3a1 1 0 000 2h10a1 1 0 000-2H7z"/></svg>
                        <p class="text-[9px] font-black uppercase tracking-[0.2em]" style="color:rgba(232,121,249,0.7);">Nivel máximo alcanzado</p>
                    </div>
                @endif

                {{-- Beneficios --}}
                <div class="grid grid-cols-2 gap-2">
                    @foreach([
                        ['active'=>$disc>0, 'label'=>$disc>0 ? "{$disc}% descuento" : 'Sin descuento', 'icon'=>'M7 7h.01M17 17h.01M19 5l-14 14M9.5 7a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0zm10 10a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z'],
                        ['active'=>in_array($lvl,['vip','leyenda']), 'label'=>in_array($lvl,['vip','leyenda'])?'Sorteo mensual':'Requiere VIP', 'icon'=>'M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4 2 2 0 010 4zm14 0a2 2 0 110-4 2 2 0 010 4z'],
                        ['active'=>in_array($lvl,['regular','vip','leyenda']), 'label'=>in_array($lvl,['regular','vip','leyenda'])?'Reserva prio.':'Requiere Regular', 'icon'=>'M13 10V3L4 14h7v7l9-11h-7z'],
                        ['active'=>$lvl==='leyenda', 'label'=>$lvl==='leyenda'?'Prod. gratis/mes':'Requiere Leyenda', 'icon'=>'M20 12v10H4V12M22 7H2v5h20V7zM12 22V7m0 0a2 2 0 10-4 0m4 0a2 2 0 114 0'],
                    ] as $ben)
                        <div class="flex items-center gap-1.5 p-2 rounded-lg" style="background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.05);">
                            <svg class="h-3 w-3 shrink-0 flex-shrink-0" fill="none" stroke="{{ $ben['active'] ? '#d4af37' : 'rgba(255,255,255,0.18)' }}" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ben['icon'] }}"/></svg>
                            <span class="text-[8px] font-bold leading-tight" style="color:{{ $ben['active'] ? '#d4af37' : 'rgba(255,255,255,0.22)' }};">{{ $ben['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Últimas transacciones --}}
                @if($recentTx->isNotEmpty())
                    <div>
                        <p class="text-[8px] font-black uppercase tracking-[0.22em] text-white/45 mb-2">Últimos movimientos</p>
                        <div class="space-y-1.5">
                            @foreach($recentTx->take(4) as $tx)
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="h-2.5 w-2.5 shrink-0" fill="none" stroke="{{ $tx->puntos > 0 ? '#4ade80' : '#f87171' }}" stroke-width="2.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $tx->puntos > 0 ? 'M12 19V5m0 0l-7 7m7-7l7 7' : 'M12 5v14m0 0l7-7m-7 7l-7-7' }}"/>
                                        </svg>
                                        <span class="text-[9px] text-white/50">{{ $tx->descripcion }}</span>
                                    </div>
                                    <span class="text-[9px] font-black shrink-0" style="color:{{ $tx->puntos > 0 ? '#4ade80' : '#f87171' }};">{{ $tx->puntos > 0 ? '+' : '' }}{{ $tx->puntos }} pts</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Sorteo ganado --}}
                @if($wonRaffle)
                    <div class="flex items-center gap-2 p-2.5 rounded-lg" style="background:rgba(232,121,249,0.05);border:1px solid rgba(232,121,249,0.15);">
                        <svg class="h-3.5 w-3.5 shrink-0" style="fill:rgba(232,121,249,0.8);" viewBox="0 0 24 24"><path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                        <div>
                            <p class="text-[9px] font-black" style="color:rgba(232,121,249,0.8);">Ganaste el sorteo de {{ $wonRaffle->mes }}</p>
                            <p class="text-[8px] text-white/50">{{ $wonRaffle->premio }}</p>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </section>

    @else
        <section class="rounded-2xl border border-white/[0.06] bg-[#111] p-8">
            <h3 class="text-sm font-black text-white uppercase tracking-widest">Panel no disponible</h3>
            <p class="mt-2 text-xs text-white/50">Tu perfil no tiene un rol configurado. Contacta a un administrador.</p>
        </section>
    @endif

    </div>{{-- /space-y-5 --}}

    {{-- CHARTS JS --}}
    @if(($adminMode??false)||($isBarberMode??false)||($isReceptionMode??false)||($isClientMode??false))
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = "'Figtree', sans-serif";
        Chart.defaults.color = 'rgba(255,255,255,0.3)';
        Chart.defaults.font.weight = 'bold';

        const scale = {
            ticks: { color: 'rgba(255,255,255,0.25)', font: { size: 10 } },
            grid:  { color: 'rgba(255,255,255,0.04)', drawBorder: false }
        };

        function makeChart(id, config) {
            const el = document.getElementById(id);
            if (el) new Chart(el, config);
        }

        @if($adminMode ?? false)
        // Init perezoso: las 4 gráficas admin se crean cuando se abre la zona
        // "Analítica avanzada" (ya visible → canvas con tamaño real).
        window.__ubInitAdminCharts = function () {
        makeChart('incomeChart', {
            type: 'line',
            data: {
                labels: @json($incomeChart['labels'] ?? []),
                datasets: [{ label: 'Ingresos ($)', data: @json($incomeChart['values'] ?? []),
                    borderColor: '#d4af37', backgroundColor: 'rgba(212,175,55,0.08)',
                    borderWidth: 2.5, fill: true, tension: 0.4,
                    pointRadius: 3, pointBackgroundColor: '#0d0d0d', pointBorderColor: '#d4af37', pointBorderWidth: 2 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{y:scale,x:scale} }
        });

        makeChart('servicesChart', {
            type: 'doughnut',
            data: {
                labels: @json($servicesChart['labels'] ?? []),
                datasets: [{ data: @json($servicesChart['values'] ?? []),
                    backgroundColor: ['#d4af37','rgba(255,255,255,0.7)','#555','#888','#333'],
                    borderWidth: 0, hoverOffset: 10 }]
            },
            options: { responsive:true, maintainAspectRatio:false, cutout:'78%',
                plugins:{ legend:{ position:'bottom', labels:{ color:'rgba(255,255,255,0.35)', usePointStyle:true, padding:16, font:{size:10,weight:'bold'} } } } }
        });

        makeChart('barberPerformanceChart', {
            type: 'bar',
            data: {
                labels: @json($barberPerformance['labels'] ?? []),
                datasets: [
                    { label:'Citas', data: @json($barberPerformance['appointments'] ?? []),
                        backgroundColor: 'rgba(59,130,246,0.7)', borderRadius:6, barThickness:20 },
                    { label:'Ingresos ($)', data: @json($barberPerformance['revenue'] ?? []),
                        backgroundColor: 'rgba(16,185,129,0.7)', borderRadius:6, barThickness:20 }
                ]
            },
            options: { responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{ display:true, labels:{ color:'rgba(255,255,255,0.35)', usePointStyle:true, padding:12, font:{size:10} } } },
                scales:{y:scale,x:scale} }
        });

        makeChart('clientTrendsChart', {
            type: 'line',
            data: {
                labels: @json($clientTrends['labels'] ?? []),
                datasets: [{ label:'Citas Completadas', data: @json($clientTrends['values'] ?? []),
                    borderColor:'#a78bfa', backgroundColor:'rgba(167,139,250,0.08)',
                    borderWidth:2.5, fill:true, tension:0.4,
                    pointRadius:3, pointBackgroundColor:'#0d0d0d', pointBorderColor:'#a78bfa', pointBorderWidth:2 }]
            },
            options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
                scales:{ y:{...scale, beginAtZero:true}, x:scale } }
        });
        }; {{-- /__ubInitAdminCharts --}}

        // AI Predictions
        (async () => {
            try {
                const tokenRes = await fetch('/api/v1/auth/get-api-token', {
                    method:'POST', headers:{ 'Content-Type':'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content||'' }
                });
                if (!tokenRes.ok) return;
                const { token } = await tokenRes.json();
                const h = { 'Content-Type':'application/json', 'Authorization':`Bearer ${token}`,
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content||'' };

                const [ir,ar,insr] = await Promise.all([
                    fetch('/api/v1/admin/predictions/income/7',{headers:h}),
                    fetch('/api/v1/admin/predictions/appointments/7',{headers:h}),
                    fetch('/api/v1/admin/predictions/insights',{headers:h})
                ]);
                if (ir.ok) { const d=await ir.json(); document.getElementById('income-forecast').textContent=d.data?.predicted_income?`$${d.data.predicted_income.toFixed(0)}`:'N/A'; }
                if (ar.ok) { const d=await ar.json(); document.getElementById('appointment-forecast').textContent=d.data?.predicted_appointments??'N/A'; }
                document.getElementById('ai-confidence').textContent='72%';
                if (insr.ok) {
                    const {data:ins={}} = await insr.json();
                    const colors={'positive':'border-emerald-500/20 bg-emerald-500/[0.04]','warning':'border-amber-500/20 bg-amber-500/[0.04]','neutral':'border-blue-500/20 bg-blue-500/[0.04]'};
                    const dots ={'positive':'bg-emerald-400','warning':'bg-amber-400','neutral':'bg-blue-400'};
                    document.getElementById('ai-insights').innerHTML = Object.values(ins).map(i=>`
                        <div class="flex items-start gap-2 p-3 rounded-xl border ${colors[i.status]||colors.neutral}">
                            <div class="h-1.5 w-1.5 rounded-full ${dots[i.status]||dots.neutral} mt-1.5 shrink-0"></div>
                            <p class="text-[10px] text-white/60">${i.message}</p>
                        </div>`).join('') || '<p class="text-[10px] text-white/45 italic">Sin insights disponibles.</p>';
                }
            } catch(e) {
                ['income-forecast','appointment-forecast','ai-confidence'].forEach(id => {
                    const el = document.getElementById(id); if(el) el.textContent='—';
                });
            }
        })();
        @endif

        @if($isBarberMode ?? false)
        makeChart('performanceChart', {
            type:'bar',
            data:{ labels:@json($performanceChart['labels']??[]),
                datasets:[{label:'Citas', data:@json($performanceChart['values']??[]),
                    backgroundColor:'#d4af37', borderRadius:8, barThickness:18}] },
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{...scale,beginAtZero:true},x:scale}}
        });
        makeChart('servicesChart', {
            type:'doughnut',
            data:{ labels:@json($servicesChart['labels']??[]),
                datasets:[{data:@json($servicesChart['values']??[]),
                    backgroundColor:['#d4af37','rgba(255,255,255,0.6)','#555','#888','#333'],
                    borderWidth:0, hoverOffset:8}] },
            options:{responsive:true,maintainAspectRatio:false,cutout:'78%',
                plugins:{legend:{position:'bottom',labels:{color:'rgba(255,255,255,0.35)',usePointStyle:true,padding:14,font:{size:10}}}}}
        });
        @endif

        @if($isReceptionMode ?? false)
        makeChart('flowChart', {
            type:'line',
            data:{ labels:@json($flow_chart['labels']??[]),
                datasets:[{label:'Citas',data:@json($flow_chart['values']??[]),
                    borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,0.08)',
                    borderWidth:2.5,fill:true,tension:0.4,pointRadius:3,pointBackgroundColor:'#0d0d0d',pointBorderColor:'#6366f1',pointBorderWidth:2}] },
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
                scales:{y:{...scale,beginAtZero:true,ticks:{...scale.ticks,stepSize:1}},x:scale}}
        });
        @endif

        @if($isClientMode ?? false)
        makeChart('visitChart', {
            type:'line',
            data:{ labels:@json($visit_chart['labels']??[]),
                datasets:[{label:'Visitas',data:@json($visit_chart['values']??[]),
                    borderColor:'#d4af37',backgroundColor:'rgba(212,175,55,0.08)',
                    borderWidth:2.5,fill:true,tension:0.4,pointRadius:4,pointBackgroundColor:'#d4af37'}] },
            options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
                scales:{y:{...scale,beginAtZero:true,ticks:{...scale.ticks,stepSize:1}},x:scale}}
        });
        @endif
    </script>
    @endif

</x-app-layout>
