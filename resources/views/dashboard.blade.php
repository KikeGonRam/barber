<x-app-layout>
    <x-slot name="header">
        @php
            $dashboardMode = ($adminMode ?? false) ? 'admin' : (($isBarberMode ?? false) ? 'barber' : (($isReceptionMode ?? false) ? 'reception' : 'client'));
            $dashboardTitleClass = match ($dashboardMode) {
                'admin' => 'ui-profile-title ui-profile-title-admin',
                'barber' => 'ui-profile-title ui-profile-title-barber',
                'reception' => 'ui-profile-title ui-profile-title-reception',
                default => 'ui-profile-title ui-profile-title-client',
            };
        @endphp
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="{{ $dashboardTitleClass }}">Dashboard <span class="text-gold">{{ ($adminMode ?? false) ? 'Administrativo' : (($isBarberMode ?? false) ? 'Profesional' : (($isReceptionMode ?? false) ? 'Operativo' : 'Cliente')) }}</span></h2>
                <p class="ui-profile-subtitle mt-2">Vista ejecutiva del rendimiento y agenda de la barbería.</p>
            </div>
            <div class="flex items-center gap-4">
                @if($adminMode ?? false)
                <form method="POST" action="{{ route('settings.maintenance.toggle') }}">
                    @csrf
                    <button type="submit" 
                            class="flex items-center gap-2 px-4 py-2 rounded-xl border {{ ($maintenanceMode ?? false) ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-white/5 border-white/10 text-muted hover:text-white' }} transition-all text-[10px] font-black uppercase tracking-widest">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <span>{{ ($maintenanceMode ?? false) ? 'Sistema en Mantenimiento' : 'Modo Mantenimiento' }}</span>
                    </button>
                </form>
                <a href="{{ route('backups.database.download') }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-xl border bg-green-500/10 border-green-500/30 text-green-300 hover:text-white hover:bg-green-500/20 transition-all text-[10px] font-black uppercase tracking-widest">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-1.105.895-2 2-2h4a2 2 0 012 2v4a2 2 0 01-2 2h-4a2 2 0 01-2-2m0-4H8a2 2 0 00-2 2v4a2 2 0 002 2h4m0-8V7a2 2 0 00-2-2H6a2 2 0 00-2 2v4m8 0v4" /></svg>
                    <span>Backup BD</span>
                </a>
                @endif
                <span class="ui-badge bg-white shadow-sm ring-1 ring-white/10 border-white/5">
                    <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse mr-2"></span>
                    Sistema Activo
                </span>
            </div>

        </div>
    </x-slot>

    <div class="space-y-6 py-4 sm:space-y-8 sm:py-6">
        @if ($adminMode ?? false)

@php
    /* ── Sparkline SVG helper (closure para evitar redeclaración) ── */
    $adminSparkline = function(array $values, int $w = 96, int $h = 28): string {
        $vals = array_values(array_filter($values, fn($v) => $v !== null));
        if (count($vals) < 2) return '';
        $max  = max($vals) ?: 1;
        $min  = min($vals);
        $rng  = ($max - $min) ?: 1;
        $step = $w / (count($vals) - 1);
        $pts  = [];
        foreach ($vals as $i => $v) {
            $x = round($i * $step, 2);
            $y = round($h - (($v - $min) / $rng) * $h, 2);
            $pts[] = "$x,$y";
        }
        return 'M ' . implode(' L ', $pts);
    };
    $incomeSpark = $adminSparkline($incomeChart['values'] ?? []);
    $barberStatuses = $kpis['barbers_status'] ?? [];

    /* ── Estado badge helper ──────────────────────────── */
    $statusMap = [
        'completada' => ['bg-emerald-500/15 text-emerald-300 border-emerald-500/25', 'Completada'],
        'pendiente'  => ['bg-amber-500/15 text-amber-300 border-amber-500/25',       'Pendiente'],
        'en_proceso' => ['bg-blue-500/15 text-blue-300 border-blue-500/25',           'En proceso'],
        'cancelada'  => ['bg-red-500/15 text-red-400 border-red-500/25',              'Cancelada'],
    ];
@endphp

<!-- ══════════════════════════════════════════════════════════ -->
<!-- ADMIN DASHBOARD                                           -->
<!-- ══════════════════════════════════════════════════════════ -->

{{-- ── 1. ACCIONES RÁPIDAS ─────────────────────────────── --}}
<section class="grid grid-cols-2 gap-3 sm:grid-cols-4 animate-slide-up">
    @foreach([
        ['href'=> route('appointments.create'),        'label'=>'Nueva Cita',     'sub'=>'Agendar servicio',    'color'=>'blue',   'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['href'=> route('clients.create'),             'label'=>'Nuevo Cliente',  'sub'=>'Alta en sistema',     'color'=>'cyan',   'icon'=>'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z'],
        ['href'=> route('payments.create'),            'label'=>'Cobrar',         'sub'=>'Registrar pago',      'color'=>'green',  'icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
        ['href'=> route('reports.index'),              'label'=>'Reportes',       'sub'=>'Ver análisis',        'color'=>'purple', 'icon'=>'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
    ] as $action)
        <a href="{{ $action['href'] }}"
           class="group flex items-center gap-3 rounded-2xl border border-white/8 bg-[#111] p-4 hover:border-{{ $action['color'] }}-500/40 hover:bg-{{ $action['color'] }}-500/5 transition-all duration-300">
            <div class="h-10 w-10 rounded-xl bg-{{ $action['color'] }}-500/10 text-{{ $action['color'] }}-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $action['icon'] }}"/></svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-black text-white truncate">{{ $action['label'] }}</p>
                <p class="text-[10px] font-bold text-muted uppercase tracking-wider truncate">{{ $action['sub'] }}</p>
            </div>
        </a>
    @endforeach
</section>

{{-- ── 2. KPI CARDS ─────────────────────────────────────── --}}
<section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

    {{-- Citas Hoy --}}
    <article class="ui-kpi-card group relative overflow-hidden animate-slide-up border-blue-500/0 hover:border-blue-500/30" style="animation-delay:0ms">
        <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-blue-500/60 via-blue-400/30 to-transparent"></div>
        <div class="flex items-start justify-between mb-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-muted">Citas Hoy</p>
            <div class="h-9 w-9 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
        </div>
        <p class="text-4xl font-black text-white leading-none mb-2">{{ $kpis['appointments_today'] }}</p>
        <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-bold bg-blue-500/10 text-blue-300 border border-blue-500/20 px-2 py-0.5 rounded-full">Sem: {{ $kpis['appointments_week'] }}</span>
            <span class="text-[10px] font-bold bg-white/5 text-muted border border-white/10 px-2 py-0.5 rounded-full">Mes: {{ $kpis['appointments_month'] }}</span>
        </div>
        @if($kpis['appointment_growth'] != 0)
            <div class="mt-3 flex items-center gap-1 text-xs font-black {{ $kpis['appointment_growth'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="{{ $kpis['appointment_growth'] >= 0 ? 'M5 10l7-7 7 7' : 'M19 14l-7 7-7-7' }}"/></svg>
                {{ abs($kpis['appointment_growth']) }}% vs mes anterior
            </div>
        @endif
    </article>

    {{-- Ingresos Hoy --}}
    <a href="{{ route('payments.index') }}" class="ui-kpi-card group relative overflow-hidden animate-slide-up hover:border-emerald-500/30 cursor-pointer" style="animation-delay:80ms">
        <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-emerald-500/60 via-emerald-400/30 to-transparent"></div>
        <div class="flex items-start justify-between mb-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-muted">Ingresos Hoy</p>
            <div class="h-9 w-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-4xl font-black text-emerald-400 leading-none mb-2">${{ number_format($kpis['income_today'], 0) }}</p>
        {{-- Sparkline mini --}}
        @if($incomeSpark)
            <svg viewBox="0 0 96 28" class="w-full h-7 mb-2" preserveAspectRatio="none">
                <path d="{{ $incomeSpark }}" fill="none" stroke="rgba(52,211,153,0.5)" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="{{ $incomeSpark }} L 96,28 L 0,28 Z" fill="rgba(52,211,153,0.06)"/>
            </svg>
        @endif
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20 px-2 py-0.5 rounded-full">Sem: ${{ number_format($kpis['income_week'], 0) }}</span>
            @if($kpis['income_growth'] != 0)
                <span class="text-[10px] font-black {{ $kpis['income_growth'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $kpis['income_growth'] >= 0 ? '↑' : '↓' }}{{ abs($kpis['income_growth']) }}%
                </span>
            @endif
        </div>
    </a>

    {{-- Clientes Activos --}}
    <a href="{{ route('clients.index') }}" class="ui-kpi-card group relative overflow-hidden animate-slide-up hover:border-cyan-500/30 cursor-pointer" style="animation-delay:160ms">
        <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-cyan-500/60 via-cyan-400/30 to-transparent"></div>
        <div class="flex items-start justify-between mb-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-muted">Clientes Activos</p>
            <div class="h-9 w-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <p class="text-4xl font-black text-white leading-none mb-2">{{ $kpis['active_clients'] }}</p>
        {{-- Barra de ratio activos/total --}}
        @php $ratio = $kpis['total_clients'] > 0 ? round(($kpis['active_clients']/$kpis['total_clients'])*100) : 0; @endphp
        <div class="mb-2">
            <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-cyan-400 rounded-full transition-all duration-700" style="width:{{ $ratio }}%"></div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-[10px] font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/20 px-2 py-0.5 rounded-full">{{ $ratio }}% activos</span>
            <span class="text-[10px] font-bold text-muted">de {{ $kpis['total_clients'] }} totales</span>
        </div>
    </a>

    {{-- Stock & Retención --}}
    <article class="ui-kpi-card group relative overflow-hidden animate-slide-up hover:border-purple-500/30" style="animation-delay:240ms">
        <div class="absolute top-0 left-0 h-0.5 w-full bg-gradient-to-r from-purple-500/60 via-purple-400/30 to-transparent"></div>
        <div class="flex items-start justify-between mb-3">
            <p class="text-[10px] font-black uppercase tracking-widest text-muted">Retención</p>
            <div class="h-9 w-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center group-hover:scale-110 transition-transform">
                <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
        </div>
        <p class="text-4xl font-black text-purple-400 leading-none mb-2">{{ number_format($kpis['retention_rate'], 1) }}<span class="text-2xl">%</span></p>
        <div class="mb-2">
            <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                <div class="h-full bg-purple-400 rounded-full" style="width:{{ min(100,$kpis['retention_rate']) }}%"></div>
            </div>
        </div>
        <span class="text-[10px] font-bold bg-purple-500/10 text-purple-300 border border-purple-500/20 px-2 py-0.5 rounded-full">
            {{ $kpis['recurring_clients'] }} recurrentes
        </span>
        @if(($kpis['low_stock_count'] ?? 0) > 0)
            <div class="mt-3 flex items-center gap-1.5 text-[10px] font-black text-amber-400">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ $kpis['low_stock_count'] }} productos con stock bajo
            </div>
        @endif
    </article>
</section>

{{-- ── 3. CUERPO PRINCIPAL: agenda + actividad reciente ─── --}}
<section class="grid grid-cols-1 gap-5 lg:grid-cols-12">

    {{-- Citas de hoy: timeline --}}
    <div class="lg:col-span-7 ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:200ms">
        <div class="flex items-center justify-between mb-7">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Agenda de Hoy</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">{{ now()->format('d \d\e F, Y') }}</p>
            </div>
            <a href="{{ route('appointments.index') }}" class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition flex items-center gap-1">
                Ver todo <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        @if($todayAppointments->isEmpty())
            <div class="flex flex-col items-center justify-center py-14 border border-dashed border-white/8 rounded-2xl">
                <svg class="h-10 w-10 text-white/10 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm font-bold text-muted">Sin citas programadas para hoy</p>
                <a href="{{ route('appointments.create') }}" class="mt-4 text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">+ Crear cita</a>
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($todayAppointments as $appt)
                    @php [$pill,$label] = $statusMap[$appt->estado] ?? ['bg-white/8 text-white/50 border-white/10','Desconocido']; @endphp
                    <div class="group flex items-center gap-4 p-3.5 rounded-2xl border border-white/5 hover:border-gold/20 hover:bg-white/[0.02] transition-all">
                        {{-- Hora --}}
                        <div class="shrink-0 w-12 text-center">
                            <p class="text-[11px] font-black text-white">{{ substr($appt->hora_inicio ?? '--:--', 0, 5) }}</p>
                        </div>
                        {{-- Divider --}}
                        <div class="w-px h-8 bg-white/8 shrink-0"></div>
                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-black text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                            <p class="text-[10px] font-bold text-muted truncate">
                                {{ $appt->service?->nombre ?? '—' }} &bull; {{ $appt->barber?->user?->name ?? '—' }}
                            </p>
                        </div>
                        {{-- Estado badge --}}
                        <span class="shrink-0 text-[9px] font-black uppercase tracking-wider border rounded-full px-2.5 py-1 {{ $pill }}">
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Panel derecho: top barbero + actividad reciente --}}
    <div class="lg:col-span-5 flex flex-col gap-5">

        {{-- Top barbero del mes --}}
        <div class="ui-card-premium p-6 relative overflow-hidden animate-slide-up" style="animation-delay:280ms">
            <div class="absolute right-0 top-0 h-full w-1/2 bg-gradient-to-l from-gold/5 to-transparent pointer-events-none"></div>
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-gold mb-4">⭐ Top Barbero del Mes</p>
            @if($kpis['top_barber_name'])
                <div class="flex items-center gap-4">
                    <div class="h-14 w-14 rounded-2xl bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/20 flex items-center justify-center text-xl font-black text-gold shrink-0">
                        {{ strtoupper(substr($kpis['top_barber_name'], 0, 2)) }}
                    </div>
                    <div>
                        <p class="text-base font-black text-white">{{ $kpis['top_barber_name'] }}</p>
                        <p class="text-[10px] font-bold text-muted uppercase tracking-wider">{{ $kpis['top_barber_total'] }} citas este mes</p>
                        <div class="flex gap-0.5 mt-1">
                            @for($s=0;$s<5;$s++)<svg class="h-3 w-3 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                        </div>
                    </div>
                </div>
            @else
                <p class="text-sm text-muted italic">Sin datos disponibles</p>
            @endif
        </div>

        {{-- Actividad reciente --}}
        <div class="ui-card-premium p-6 flex-1 animate-slide-up" style="animation-delay:340ms">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-[11px] font-black uppercase tracking-widest text-white">Actividad Reciente</h3>
                <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span>
            </div>
            <div class="space-y-3">
                @forelse($recentAppointments->take(5) as $appt)
                    @php [$pill,$label] = $statusMap[$appt->estado] ?? ['bg-white/8 text-white/50 border-white/10','—']; @endphp
                    <div class="flex items-center gap-3">
                        <div class="h-8 w-8 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center text-[10px] font-black text-gold shrink-0">
                            {{ strtoupper(substr($appt->barber?->user?->name ?? 'B', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-white truncate">{{ $appt->client?->user?->name ?? 'Cliente' }}</p>
                            <p class="text-[9px] text-muted truncate">{{ \Carbon\Carbon::parse($appt->fecha)->format('d M') }} · {{ substr($appt->hora_inicio,0,5) }}</p>
                        </div>
                        <span class="text-[8px] font-black uppercase border rounded-full px-2 py-0.5 shrink-0 {{ $pill }}">{{ $label }}</span>
                    </div>
                @empty
                    <p class="text-xs text-muted italic">Sin actividad reciente</p>
                @endforelse
            </div>
        </div>
    </div>
</section>

{{-- ── 4. ESTACIONES EN VIVO ────────────────────────────── --}}
<section class="ui-card-premium p-6 sm:p-8 relative overflow-hidden animate-slide-up" style="animation-delay:300ms">
    <div class="absolute right-0 top-0 h-full w-64 bg-gradient-to-l from-gold/4 to-transparent pointer-events-none"></div>
    <div class="flex items-center justify-between mb-7 relative z-10">
        <div>
            <h3 class="text-sm font-black text-white uppercase tracking-widest">Estaciones en Vivo</h3>
            <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Ocupación en tiempo real</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-green-500/30 bg-green-500/10 px-3 py-1 text-[9px] font-black uppercase text-green-400">
            <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span> Live
        </span>
    </div>

    @if(! empty($barberStatuses))
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4 relative z-10">
            @foreach($barberStatuses as $i => $st)
                <div class="group rounded-2xl border {{ $st['is_busy'] ? 'border-red-500/25 bg-red-500/5' : 'border-emerald-500/20 bg-emerald-500/5' }} p-4 text-center hover:scale-105 transition-transform duration-300">
                    {{-- Avatar --}}
                    <div class="relative inline-flex mb-3">
                        <div class="h-12 w-12 rounded-2xl bg-[#1a1a1a] border border-white/10 flex items-center justify-center text-gold font-black">
                            {{ strtoupper(substr($st['name'], 0, 2)) }}
                        </div>
                        <span class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-[#141414] {{ $st['is_busy'] ? 'bg-red-500 animate-pulse' : 'bg-emerald-500' }}"></span>
                    </div>
                    <p class="text-[11px] font-black text-white truncate leading-tight">{{ explode(' ', $st['name'])[0] }}</p>
                    <p class="text-[9px] font-black uppercase tracking-wider mt-0.5 {{ $st['is_busy'] ? 'text-red-400' : 'text-emerald-400' }}">
                        {{ $st['is_busy'] ? 'Ocupado' : 'Libre' }}
                    </p>
                    @if($st['is_busy'])
                        <div class="mt-2.5">
                            <div class="h-1 w-full bg-white/5 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-gold to-gold-dim rounded-full transition-all duration-700" style="width:{{ $st['progress'] }}%"></div>
                            </div>
                            <p class="text-[9px] text-muted mt-1">{{ $st['progress'] }}%</p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="relative z-10 ui-empty-state py-12">
            <p class="ui-empty-state-copy">Sin barberos activos en este momento</p>
        </div>
    @endif
</section>

{{-- ── 5. PREDICCIONES IA ───────────────────────────────── --}}
<section class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:350ms">
    <div class="flex items-center justify-between mb-7">
        <div>
            <h3 class="text-sm font-black text-white uppercase tracking-widest flex items-center gap-2">
                <span class="h-5 w-5 rounded-lg bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-[10px]">AI</span>
                Predicciones con IA
            </h3>
            <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Análisis predictivo — próximos 7 días</p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-500/30 bg-indigo-500/10 px-3 py-1 text-[9px] font-black uppercase text-indigo-400">Beta</span>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        @foreach([
            ['id'=>'income-forecast',      'label'=>'Ingresos Estimados',  'sub'=>'Próximos 7 días', 'color'=>'emerald', 'icon'=>'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
            ['id'=>'appointment-forecast', 'label'=>'Citas Estimadas',     'sub'=>'Próximos 7 días', 'color'=>'blue',    'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['id'=>'ai-confidence',        'label'=>'Confianza del Modelo','sub'=>'Precisión IA',    'color'=>'indigo',  'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ] as $ai)
            <div class="rounded-2xl border border-white/6 bg-white/[0.02] p-6 hover:border-{{ $ai['color'] }}-500/30 hover:bg-{{ $ai['color'] }}-500/5 transition-all">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-muted">{{ $ai['label'] }}</p>
                        <p class="text-[10px] text-muted mt-0.5">{{ $ai['sub'] }}</p>
                    </div>
                    <div class="h-9 w-9 rounded-xl bg-{{ $ai['color'] }}-500/10 text-{{ $ai['color'] }}-400 flex items-center justify-center shrink-0">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $ai['icon'] }}"/></svg>
                    </div>
                </div>
                <p id="{{ $ai['id'] }}" class="text-3xl font-black text-{{ $ai['color'] }}-400">
                    <span class="inline-block w-8 h-1.5 bg-{{ $ai['color'] }}-500/30 rounded animate-pulse"></span>
                </p>
                <p class="text-[10px] text-muted mt-3 italic ai-sub-{{ $ai['id'] }}">Cargando...</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 pt-6 border-t border-white/6">
        <h4 class="text-[11px] font-black uppercase tracking-widest text-white mb-4">Insights Recomendados</h4>
        <div id="ai-insights" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="flex items-start gap-3 p-4 rounded-xl border border-white/6 bg-white/[0.02] animate-pulse">
                <div class="h-2 w-2 rounded-full bg-amber-400 mt-1.5 shrink-0"></div>
                <p class="text-xs text-muted">Cargando análisis de inteligencia artificial...</p>
            </div>
        </div>
    </div>
</section>

{{-- ── 6. GRÁFICAS 2×2 ─────────────────────────────────── --}}
<section class="grid grid-cols-1 gap-5 lg:grid-cols-2">

    {{-- Ingresos semanales --}}
    <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:380ms">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Tendencia de Ingresos</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Últimas 8 semanas</p>
            </div>
            <div class="h-2 w-2 rounded-full bg-emerald-400"></div>
        </div>
        @if(!empty(array_filter($incomeChart['values'] ?? [])))
            <div class="h-[260px]"><canvas id="incomeChart"></canvas></div>
        @else
            <div class="h-[260px] ui-empty-state"><p class="ui-empty-state-copy">Sin ingresos registrados aún.</p></div>
        @endif
    </article>

    {{-- Demanda de servicios --}}
    <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:440ms">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Demanda de Servicios</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Distribución por tipo</p>
            </div>
            <div class="h-2 w-2 rounded-full bg-gold"></div>
        </div>
        @if(!empty(array_filter($servicesChart['values'] ?? [])))
            <div class="h-[260px]"><canvas id="servicesChart"></canvas></div>
        @else
            <div class="h-[260px] ui-empty-state"><p class="ui-empty-state-copy">Sin servicios registrados.</p></div>
        @endif
    </article>

    {{-- Desempeño de barberos --}}
    <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:480ms">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Desempeño Barberos</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Citas + ingresos este mes</p>
            </div>
            <div class="flex gap-3 text-[9px] font-black uppercase text-muted">
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-blue-500"></span>Citas</span>
                <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span>Ingresos</span>
            </div>
        </div>
        @if(!empty(array_filter($barberPerformance['appointments'] ?? [])))
            <div class="h-[260px]"><canvas id="barberPerformanceChart"></canvas></div>
        @else
            <div class="h-[260px] ui-empty-state"><p class="ui-empty-state-copy">Sin datos de desempeño.</p></div>
        @endif
    </article>

    {{-- Tendencias de clientes --}}
    <article class="ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:520ms">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Tendencia de Clientes</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Citas completadas · mes actual</p>
            </div>
            <div class="h-2 w-2 rounded-full bg-purple-400"></div>
        </div>
        @if(!empty(array_filter($clientTrends['values'] ?? [])))
            <div class="h-[260px]"><canvas id="clientTrendsChart"></canvas></div>
        @else
            <div class="h-[260px] ui-empty-state"><p class="ui-empty-state-copy">Sin datos de tendencias.</p></div>
        @endif
    </article>
</section>

{{-- ── 7. FILA INFERIOR: REPORTES + CHATBOT ─────────────── --}}
<section class="grid grid-cols-1 gap-5 lg:grid-cols-12">

    {{-- Reportes rápidos --}}
    <div class="lg:col-span-5 ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:540ms">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-sm font-black text-white uppercase tracking-widest">Reportes Rápidos</h3>
            <a href="{{ route('reports.index') }}" class="text-[9px] font-black uppercase tracking-widest text-muted hover:text-gold transition">Ver todos &rarr;</a>
        </div>
        <div class="grid grid-cols-2 gap-3">
            @foreach([
                ['href'=> route('reports.index'), 'label'=>'Mensual',    'sub'=>'PDF KPIs',         'color'=>'blue',   'icon'=>'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z'],
                ['href'=> route('reports.index'), 'label'=>'Ingresos',   'sub'=>'CSV ventas',       'color'=>'emerald','icon'=>'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                ['href'=> route('clients.index'), 'label'=>'Clientes',   'sub'=>'CRM & retención',  'color'=>'purple', 'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['href'=> route('inventory.products.index'), 'label'=>'Inventario','sub'=>'Stock y alertas','color'=>'amber','icon'=>'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ] as $rep)
                <a href="{{ $rep['href'] }}"
                   class="group flex items-center gap-3 rounded-2xl border border-white/8 bg-white/[0.02] p-4 hover:border-{{ $rep['color'] }}-500/35 hover:bg-{{ $rep['color'] }}-500/5 transition-all">
                    <div class="h-9 w-9 rounded-xl bg-{{ $rep['color'] }}-500/10 text-{{ $rep['color'] }}-400 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $rep['icon'] }}"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-black text-white">{{ $rep['label'] }}</p>
                        <p class="text-[9px] text-muted font-bold uppercase tracking-wider">{{ $rep['sub'] }}</p>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Backup BD --}}
        <div class="mt-4 pt-4 border-t border-white/6">
            <a href="{{ route('backups.database.download') }}"
               class="flex items-center justify-between w-full px-4 py-3 rounded-xl border border-emerald-500/20 bg-emerald-500/5 text-emerald-300 hover:bg-emerald-500/10 transition-all text-[10px] font-black uppercase tracking-widest group">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descargar Backup BD
                </div>
                <svg class="h-3.5 w-3.5 opacity-50 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
    </div>

    {{-- Telemetría Chatbot --}}
    <div class="lg:col-span-7 ui-card-premium p-6 sm:p-8 animate-slide-up" style="animation-delay:580ms">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Telemetría Chatbot</h3>
                <p class="text-[10px] text-muted font-bold uppercase mt-0.5">Últimos {{ $chatbotTelemetry['window_days'] ?? 7 }} días</p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[9px] font-black uppercase text-muted">
                <span class="h-1.5 w-1.5 rounded-full bg-green-400 animate-pulse"></span> Operational
            </span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            @foreach([
                ['label'=>'Eventos',         'val'=> $chatbotTelemetry['total_requests'] ?? 0,                        'fmt'=>'%s',        'color'=>'blue'],
                ['label'=>'Error Rate',      'val'=> number_format($chatbotTelemetry['error_rate_pct'] ?? 0, 2).'%',  'fmt'=>'%s',        'color'=>'red'],
                ['label'=>'Latencia Prom.',  'val'=> ($chatbotTelemetry['avg_latency_ms'] ?? 0).'ms',                 'fmt'=>'%s',        'color'=>'sky'],
                ['label'=>'Costo Est.',      'val'=> '$'.number_format($chatbotTelemetry['estimated_cost_usd'] ?? 0, 4),'fmt'=>'%s',      'color'=>'emerald'],
            ] as $tel)
                <div class="rounded-2xl border border-white/6 bg-white/[0.02] p-4 hover:border-{{ $tel['color'] }}-500/25 transition-colors">
                    <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-1.5">{{ $tel['label'] }}</p>
                    <p class="text-xl font-black text-{{ $tel['color'] }}-400">{{ $tel['val'] }}</p>
                </div>
            @endforeach
        </div>

        @if(!empty($chatbotTelemetry['top_sources']))
            <div class="rounded-2xl border border-white/6 bg-white/[0.015] p-4">
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-3">Top Fuentes</p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach($chatbotTelemetry['top_sources'] as $source => $count)
                        <div class="rounded-xl border border-white/6 bg-white/[0.02] px-3 py-2 flex items-center justify-between hover:border-gold/20 transition-colors">
                            <span class="text-[10px] font-bold text-white uppercase truncate">{{ str_replace('_', ' ', $source) }}</span>
                            <span class="text-[10px] font-black text-gold ml-2 shrink-0">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

        @elseif ($isBarberMode ?? false)
            <!-- ========================================== -->
            <!-- BARBER DASHBOARD                           -->
            <!-- ========================================== -->
            
            <!-- Welcome -->
            <section class="ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-gold/5 blur-3xl"></div>
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                    <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold to-gold-dim flex items-center justify-center text-black shadow-lg animate-float flex-shrink-0">
                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 class="ui-profile-title ui-profile-title-barber uppercase">¡Hola, Maestro <span class="text-gradient-gold">{{ explode(' ', auth()->user()->name)[0] }}</span>!</h3>
                        <p class="mt-2 text-muted max-w-xl text-lg leading-relaxed">Tu maestría define nuestro estándar. Tienes {{ $kpis['appointments_today'] }} servicios programados para hoy.</p>
                    </div>
                </div>
            </section>

            <!-- KPIs -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Citas Hoy</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_today'] }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Citas del Mes</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_month'] }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Ingresos Mes</p>
                    <p class="ui-kpi-value mt-1 text-green-400">${{ number_format($kpis['income_month'], 2) }}</p>
                </article>
                <article class="ui-kpi-card text-center border-white/5">
                    <p class="ui-kpi-label">Rating</p>
                    <p class="ui-kpi-value mt-1 text-gold">{{ $kpis['rating'] }} <span class="text-xs">★</span></p>
                </article>
            </section>

            <!-- Charts -->
            <section class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                <article class="ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Productividad Semanal</h3>
                    @php
                        $hasPerformanceSeries = ! empty(array_filter($performanceChart['values'] ?? []));
                    @endphp
                    @if($hasPerformanceSeries)
                        <div class="h-[280px]">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Sin citas suficientes para mostrar productividad.</p>
                        </div>
                    @endif
                </article>
                <article class="ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Top de Especialidades</h3>
                    @php
                        $hasBarberServicesSeries = ! empty(array_filter($servicesChart['values'] ?? []));
                    @endphp
                    @if($hasBarberServicesSeries)
                        <div class="h-[280px]">
                            <canvas id="servicesChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay especialidades suficientes para graficar.</p>
                        </div>
                    @endif
                </article>
            </section>

            <!-- Actions -->
            <section class="flex flex-wrap gap-4 pt-4">
                <a href="{{ route('barber.agenda') }}" class="ui-btn px-12 py-4">Gestionar Mi Agenda</a>
                <a href="{{ route('barber.profile.edit') }}" class="ui-btn-secondary px-12 py-4">Ver Mi Perfil</a>
            </section>

        @elseif ($isReceptionMode ?? false)
            <!-- ========================================== -->
            <!-- RECEPTIONIST DASHBOARD                     -->
            <!-- ========================================== -->
            
            <!-- Welcome & Highlights -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-indigo-500/5 blur-3xl"></div>
                    <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                        <div class="h-20 w-20 rounded-3xl bg-indigo-600 flex items-center justify-center text-white shadow-lg animate-float flex-shrink-0">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21v-2a4 4 0 00-4-4H9a4 4 0 00-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
                        </div>
                        <div>
                            <h3 class="ui-profile-title ui-profile-title-reception uppercase">¡Hola, {{ explode(' ', auth()->user()->name)[0] }}!</h3>
                            <p class="mt-2 ui-profile-subtitle">Centro de mando activo. Gestiona el flujo de excelencia hoy.</p>
                        </div>
                    </div>
                </div>
                <div class="ui-card-premium p-6 sm:p-8 lg:p-10 flex flex-col justify-center text-center">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Cobros Pendientes</p>
                    <p class="text-4xl sm:text-5xl font-black text-white">{{ $kpis['pending_payments'] }}</p>
                    <a href="{{ route('payments.create') }}" class="mt-4 text-[9px] font-black text-gold hover:text-white transition uppercase tracking-widest">Resolver Ahora &rarr;</a>
                </div>
            </section>

            <!-- KPIs -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                <article class="ui-kpi-card border-l-4 border-indigo-500">
                    <p class="ui-kpi-label">Citas Hoy</p>
                    <p class="ui-kpi-value mt-1">{{ $kpis['appointments_today'] }}</p>
                </article>
                <article class="ui-kpi-card border-l-4 border-green-500">
                    <p class="ui-kpi-label">Nuevos Clientes</p>
                    <p class="ui-kpi-value mt-1 text-green-400">{{ $kpis['new_clients_today'] }}</p>
                </article>
                <article class="ui-kpi-card border-l-4 border-red-500">
                    <p class="ui-kpi-label">Suministros Críticos</p>
                    <p class="ui-kpi-value mt-1 text-red-400">{{ $kpis['low_stock_count'] }}</p>
                </article>
            </section>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Next Appointments -->
                <section class="lg:col-span-7 ui-card-premium p-6 sm:p-8">
                    <div class="mb-8 flex items-center justify-between">
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">Próximas Llegadas</h3>
                        <a href="{{ route('appointments.index') }}" class="text-[9px] font-black text-muted hover:text-gold transition uppercase tracking-widest">Agenda Full &rarr;</a>
                    </div>
                    <div class="space-y-4">
                        @forelse($nextAppointments as $appt)
                            <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5 hover:border-indigo-500/30 transition-all">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 rounded-xl bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-black text-xs">{{ substr($appt->hora_inicio, 0, 2) }}</div>
                                    <div>
                                        <p class="text-sm font-black text-white uppercase">{{ $appt->client?->user?->name }}</p>
                                        <p class="text-[9px] uppercase font-bold text-muted">{{ $appt->service?->nombre }} • Con {{ $appt->barber?->user?->name }}</p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xs font-black text-white">{{ substr($appt->hora_inicio, 0, 5) }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-center py-10 text-muted italic text-sm border border-dashed border-white/5 rounded-2xl">Sin llegadas próximas.</p>
                        @endforelse
                    </div>
                </section>

                <!-- Flow Chart -->
                <section class="lg:col-span-5 ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-8">Flujo Operativo (Horas)</h3>
                    @php
                        $hasFlowSeries = ! empty(array_filter($flow_chart['values'] ?? []));
                    @endphp
                    @if($hasFlowSeries)
                        <div class="h-[300px]">
                            <canvas id="flowChart"></canvas>
                        </div>
                    @else
                        <div class="h-[300px] ui-empty-state">
                            <p class="ui-empty-state-copy">Todavía no hay flujo operativo para este día.</p>
                        </div>
                    @endif
                </section>
            </div>

            <!-- Global Actions -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 pt-4">
                <a href="{{ route('appointments.create') }}" class="ui-btn py-4">Nueva Cita</a>
                <a href="{{ route('clients.create') }}" class="ui-btn-secondary py-4">Alta Cliente</a>
                <a href="{{ route('payments.create') }}" class="ui-btn-secondary py-4">Cobrar Servicio</a>
            </section>

        @elseif ($isClientMode ?? false)
            <!-- ========================================== -->
            <!-- CUSTOMER DASHBOARD                         -->
            <!-- ========================================== -->
            
            <!-- Welcome Elite -->
            <section class="ui-card-premium p-6 sm:p-8 lg:p-10 relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-gold/5 blur-3xl group-hover:bg-gold/10 transition-all duration-700"></div>
                <div class="relative z-10 flex flex-col sm:flex-row items-center gap-8">
                    <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold to-gold-dim flex items-center justify-center text-black shadow-lg animate-float flex-shrink-0">
                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    </div>
                    <div>
                        <h3 class="ui-profile-title ui-profile-title-client uppercase leading-none">¡Bienvenido, <span class="text-gradient-gold">{{ explode(' ', auth()->user()->name)[0] }}</span>!</h3>
                        <p class="mt-2 ui-profile-subtitle max-w-xl">Tu estilo es nuestra prioridad. Hoy tienes el estatus de <span class="text-white font-black uppercase">{{ $kpis['membership_status'] }}</span> en BarberPro.</p>
                    </div>
                </div>
            </section>

            <!-- Style Metrics -->
            <section class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Visitas Totales</p>
                    <p class="text-4xl font-black text-white">{{ $kpis['total_appointments'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Estatus Elite</p>
                    <p class="text-2xl font-black text-white uppercase">{{ $kpis['membership_status'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Experto Favorito</p>
                    <p class="text-base font-black text-white truncate px-2">{{ $kpis['favorite_barber'] }}</p>
                </article>
                <article class="ui-kpi-card text-center group hover:border-gold/30 transition-all">
                    <p class="text-[10px] font-black uppercase text-gold tracking-widest mb-2">Puntos Acumulados</p>
                    <p class="text-4xl font-black text-white">{{ $kpis['completed_appointments'] * 10 }}</p>
                </article>
            </section>

            <!-- Spotlight: Next Appointment -->
            @if($nextAppointment)
                <section class="ui-card-premium p-0 overflow-hidden border-gold/30 gold-glow">
                    <div class="flex flex-col md:flex-row">
                        <div class="bg-gradient-to-br from-gold to-gold-dim p-8 md:w-72 flex flex-col justify-center items-center text-black">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2 opacity-70">Tu Cita de Oro</p>
                            <p class="text-4xl font-black">{{ \Carbon\Carbon::parse($nextAppointment->fecha)->format('d M') }}</p>
                            <p class="text-xl font-bold mt-1">{{ substr($nextAppointment->hora_inicio, 0, 5) }}</p>
                        </div>
                        <div class="p-6 sm:p-8 lg:p-10 flex-1 flex flex-col sm:flex-row justify-between items-center gap-8 bg-white/5 backdrop-blur-xl">
                            <div>
                                <h3 class="text-2xl font-black text-white uppercase tracking-tight">{{ $nextAppointment->service?->nombre }}</h3>
                                <p class="text-gold font-bold uppercase tracking-widest text-xs mt-1">Con el Maestro {{ $nextAppointment->barber?->user?->name }}</p>
                            </div>
                            <a href="{{ route('client.appointments.index') }}" class="ui-btn px-12 py-4">Ver Detalles</a>
                        </div>
                    </div>
                </section>
            @endif

            <!-- Progress & Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <section class="lg:col-span-7 ui-card-premium p-6 sm:p-8">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest mb-8">Frecuencia de Cuidado Personal</h3>
                    @php
                        $hasVisitSeries = ! empty(array_filter($visit_chart['values'] ?? []));
                    @endphp
                    @if($hasVisitSeries)
                        <div class="h-[280px]">
                            <canvas id="visitChart"></canvas>
                        </div>
                    @else
                        <div class="h-[280px] ui-empty-state">
                            <p class="ui-empty-state-copy">Aún no hay historial suficiente para graficar visitas.</p>
                        </div>
                    @endif
                </section>
                
                <section class="lg:col-span-5 ui-card-premium p-8 flex flex-col items-center justify-center relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gold/5 scale-0 group-hover:scale-150 transition-transform duration-1000 rounded-full"></div>
                    <div class="text-center relative z-10 w-full">
                        <p class="text-[10px] font-black text-gold uppercase tracking-[0.3em] mb-6">Camino a la Leyenda</p>
                        @php
                            $target = $kpis['completed_appointments'] < 5 ? 5 : 10;
                            $needed = max(0, $target - $kpis['completed_appointments']);
                            $progress = min(100, ($kpis['completed_appointments'] / $target) * 100);
                        @endphp
                        <p class="text-lg font-bold text-white mb-4 leading-tight">Faltan <span class="text-3xl font-black text-gradient-gold px-1">{{ $needed }}</span> citas <br> para ser {{ $target == 5 ? 'V.I.P' : 'Leyenda' }}</p>
                        <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden border border-white/10">
                            <div class="h-full bg-gold shadow-[0_0_10px_rgba(212,175,55,0.5)] transition-all duration-1000" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Quick Access -->
            <section class="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4">
                <a href="{{ route('client.appointments.create') }}" class="ui-card-premium p-8 flex items-center justify-between group hover:border-gold/50">
                    <div><h4 class="text-xl font-black text-white uppercase">Agendar Cita</h4><p class="text-xs text-muted mt-1 uppercase tracking-widest">Elige tu ritual</p></div>
                    <div class="h-12 w-12 rounded-2xl bg-gold/10 text-gold flex items-center justify-center group-hover:bg-gold group-hover:text-black transition-all"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></div>
                </a>
                <a href="{{ route('client.appointments.index') }}" class="ui-card-premium p-8 flex items-center justify-between group hover:border-white/30">
                    <div><h4 class="text-xl font-black text-white uppercase">Mis Citas</h4><p class="text-xs text-muted mt-1 uppercase tracking-widest">Historial Premium</p></div>
                    <div class="h-12 w-12 rounded-2xl bg-white/10 text-white flex items-center justify-center group-hover:bg-white group-hover:text-black transition-all"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                </a>
            </section>
        @else
            <section class="ui-card-premium p-8">
                <h3 class="text-lg font-black text-white uppercase tracking-widest">Panel no disponible</h3>
                <p class="mt-2 text-sm text-muted">
                    Tu perfil no tiene un modo de dashboard configurado todavia. Contacta a un administrador para completar la configuracion de rol/perfil.
                </p>
            </section>
        @endif
    </div>

    @if(($adminMode ?? false) || ($isBarberMode ?? false) || ($isReceptionMode ?? false) || ($isClientMode ?? false))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            Chart.defaults.font.family = "'Figtree', sans-serif";
            Chart.defaults.color = '#737373';
            Chart.defaults.font.weight = 'bold';
            
            const chartScale = {
                ticks: { color: '#737373', font: { size: 10 } },
                grid: { color: 'rgba(255,255,255,0.03)', drawBorder: false }
            };

            @if($adminMode ?? false)
                const incomeCtx = document.getElementById('incomeChart');
                if (incomeCtx) {
                    new Chart(incomeCtx, {
                        type: 'line',
                        data: {
                            labels: @json($incomeChart['labels'] ?? []),
                            datasets: [{
                                label: 'Ingresos ($)',
                                data: @json($incomeChart['values'] ?? []),
                                borderColor: '#d4af37',
                                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#000',
                                pointBorderColor: '#d4af37'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: chartScale, x: chartScale }
                        }
                    });
                }

                // Chatbot telemetry trend charts
                // (Preparado para UI/UX - datos calculados en backend en $chatbotTelemetry['trend_chart'])
            @endif

            @if($isBarberMode ?? false)
                const perfCtx = document.getElementById('performanceChart');
                if (perfCtx) {
                    new Chart(perfCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($performanceChart['labels'] ?? []),
                            datasets: [{
                                label: 'Citas',
                                data: @json($performanceChart['values'] ?? []),
                                backgroundColor: '#d4af37',
                                borderRadius: 8,
                                barThickness: 20
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: chartScale, x: chartScale }
                        }
                    });
                }
            @endif

            @if($isReceptionMode ?? false)
                const flowCtx = document.getElementById('flowChart');
                if (flowCtx) {
                    new Chart(flowCtx, {
                        type: 'line',
                        data: {
                            labels: @json($flow_chart['labels'] ?? []),
                            datasets: [{
                                label: 'Citas',
                                data: @json($flow_chart['values'] ?? []),
                                borderColor: '#6366f1',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointBackgroundColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: 1 } }, x: chartScale }
                        }
                    });
                }
            @endif

            @if($isClientMode ?? false)
                const visitCtx = document.getElementById('visitChart');
                if (visitCtx) {
                    new Chart(visitCtx, {
                        type: 'line',
                        data: {
                            labels: @json($visit_chart['labels'] ?? []),
                            datasets: [{
                                label: 'Visitas',
                                data: @json($visit_chart['values'] ?? []),
                                borderColor: '#d4af37',
                                backgroundColor: 'rgba(212, 175, 55, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#d4af37'
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: 1 } }, x: chartScale }
                        }
                    });
                }
            @endif

            const servicesCtx = document.getElementById('servicesChart');
            if (servicesCtx) {
                new Chart(servicesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: @json($servicesChart['labels'] ?? []),
                        datasets: [{
                            data: @json($servicesChart['values'] ?? []),
                            backgroundColor: ['#d4af37', '#ffffff', '#444444', '#888888', '#222222'],
                            borderWidth: 0,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        cutout: '80%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#b0b0b0', usePointStyle: true, padding: 20, font: { size: 11, weight: 'bold' } }
                            }
                        }
                    }
                });
            }

            @if($adminMode ?? false)
                // Barber Performance Chart
                const barberPerfCtx = document.getElementById('barberPerformanceChart');
                if (barberPerfCtx) {
                    new Chart(barberPerfCtx, {
                        type: 'bar',
                        data: {
                            labels: @json($barberPerformance['labels'] ?? []),
                            datasets: [
                                {
                                    label: 'Citas Completadas',
                                    data: @json($barberPerformance['appointments'] ?? []),
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 8,
                                    barThickness: 30,
                                    order: 2
                                },
                                {
                                    label: 'Ingresos ($)',
                                    data: @json($barberPerformance['revenue'] ?? []),
                                    backgroundColor: '#10b981',
                                    borderRadius: 8,
                                    barThickness: 30,
                                    order: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { 
                                legend: { 
                                    display: true,
                                    labels: { color: '#b0b0b0', usePointStyle: true, padding: 15, font: { size: 11, weight: 'bold' } }
                                } 
                            },
                            scales: { 
                                y: chartScale, 
                                x: chartScale 
                            }
                        }
                    });
                }

                // Client Trends Chart
                const clientTrendsCtx = document.getElementById('clientTrendsChart');
                if (clientTrendsCtx) {
                    new Chart(clientTrendsCtx, {
                        type: 'line',
                        data: {
                            labels: @json($clientTrends['labels'] ?? []),
                            datasets: [{
                                label: 'Citas Completadas',
                                data: @json($clientTrends['values'] ?? []),
                                borderColor: '#a78bfa',
                                backgroundColor: 'rgba(167, 139, 250, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 5,
                                pointBackgroundColor: '#a78bfa',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: { 
                                y: { ...chartScale, beginAtZero: true, ticks: { ...chartScale.ticks, stepSize: Math.ceil(Math.max(...(@json($clientTrends['values'] ?? []) || [0])) / 5) || 1 } }, 
                                x: chartScale 
                            }
                        }
                    });
                }
            @endif

            @if($adminMode ?? false)
                // Load API Token for Dashboard
                let apiToken = null;

                async function getApiToken() {
                    try {
                        const response = await fetch('/api/v1/auth/get-api-token', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            apiToken = data.token;
                            return true;
                        }
                    } catch (error) {
                        console.error('Error getting API token:', error);
                    }
                    return false;
                }

                // Load AI Predictions
                async function loadPredictions() {
                    // Get API token first if we don't have one
                    if (!apiToken) {
                        const hasToken = await getApiToken();
                        if (!hasToken) {
                            console.error('Failed to obtain API token');
                            document.getElementById('income-forecast').textContent = 'Error';
                            document.getElementById('appointment-forecast').textContent = 'Error';
                            return;
                        }
                    }

                    try {
                        const headers = {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${apiToken}`,
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        };

                        const [incomeRes, appointmentsRes, insightsRes] = await Promise.all([
                            fetch('/api/v1/admin/predictions/income/7', { headers }),
                            fetch('/api/v1/admin/predictions/appointments/7', { headers }),
                            fetch('/api/v1/admin/predictions/insights', { headers })
                        ]);

                        if (incomeRes.ok) {
                            const incomeData = await incomeRes.json();
                            document.getElementById('income-forecast').textContent = 
                                incomeData.data?.predicted_income ? `$${incomeData.data.predicted_income.toFixed(2)}` : 'N/A';
                        }

                        if (appointmentsRes.ok) {
                            const appointmentData = await appointmentsRes.json();
                            document.getElementById('appointment-forecast').textContent = 
                                appointmentData.data?.predicted_appointments ?? 'N/A';
                        }

                        if (insightsRes.ok) {
                            const insightsData = await insightsRes.json();
                            const insights = insightsData.data || {};
                            
                            let insightsHtml = '';
                            Object.entries(insights).forEach(([key, insight]) => {
                                const statusColors = {
                                    positive: 'border-green-500/30 bg-green-500/5',
                                    warning: 'border-yellow-500/30 bg-yellow-500/5',
                                    neutral: 'border-blue-500/30 bg-blue-500/5'
                                };
                                const color = statusColors[insight.status] || statusColors.neutral;
                                
                                insightsHtml += `
                                    <div class="flex items-start gap-3 p-4 rounded-lg border ${color} animate-slide-up">
                                        <div class="h-2 w-2 rounded-full ${insight.status === 'positive' ? 'bg-green-400' : insight.status === 'warning' ? 'bg-yellow-400' : 'bg-blue-400'} mt-1.5 flex-shrink-0"></div>
                                        <div>
                                            <p class="text-xs font-bold text-white">${insight.message}</p>
                                            ${insight.avg_daily ? `<p class="text-[10px] text-muted mt-1">Promedio: ${insight.avg_daily}</p>` : ''}
                                        </div>
                                    </div>
                                `;
                            });
                            
                            document.getElementById('ai-insights').innerHTML = insightsHtml || '<p class="text-xs text-muted">Sin insights disponibles.</p>';
                        }

                        // Update confidence (mock for now)
                        document.getElementById('ai-confidence').textContent = '72%';
                    } catch (error) {
                        console.error('Error loading predictions:', error);
                        document.getElementById('income-forecast').textContent = 'Error';
                        document.getElementById('appointment-forecast').textContent = 'Error';
                    }
                }

                // Load predictions when page loads
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', loadPredictions);
                } else {
                    loadPredictions();
                }
            @endif
        </script>
    @endif
</x-app-layout>
