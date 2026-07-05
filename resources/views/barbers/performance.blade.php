<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Rendimiento de <span class="text-gold">{{ explode(' ', $barber->user?->name ?? 'Barbero')[0] }}</span></h2>
                <p class="ui-subtitle">KPIs, historial de citas y análisis de desempeño.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('barbers.edit', $barber) }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Editar Perfil
                </a>
                <a href="{{ route('barbers.index') }}" class="ui-btn-secondary px-5 text-[11px] tracking-widest">
                    <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                    Equipo
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-4">

        {{-- ── PERFIL BANNER ───────────────────────────────── --}}
        <section class="ui-card-premium p-0 overflow-hidden">
            <div class="flex flex-col sm:flex-row">
                {{-- Avatar --}}
                <div class="flex flex-col items-center justify-center p-8 sm:w-56 border-b sm:border-b-0 sm:border-r border-white/6 bg-[#0f0f0f] relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-gold/4 to-transparent pointer-events-none"></div>
                    @if($barber->foto)
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                             class="h-24 w-24 rounded-3xl object-cover border border-gold/20 mb-4 relative z-10" loading="lazy"
                             alt="Foto de {{ $barber->user?->name }}">
                    @else
                        <div class="h-24 w-24 rounded-3xl bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/20 flex items-center justify-center text-4xl font-black text-gold mb-4 relative z-10">
                            {{ strtoupper(substr($barber->user?->name ?? 'B', 0, 2)) }}
                        </div>
                    @endif
                    <h3 class="text-base font-black text-white text-center relative z-10">{{ $barber->user?->name }}</h3>
                    <p class="text-[9px] text-muted text-center mt-0.5 relative z-10">{{ $barber->user?->email }}</p>
                    <span class="mt-3 inline-flex items-center gap-1.5 rounded-full border {{ $barber->activo ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-400' : 'border-white/10 text-muted' }} px-3 py-1 text-[9px] font-black uppercase tracking-wider relative z-10">
                        <span class="h-1.5 w-1.5 rounded-full {{ $barber->activo ? 'bg-emerald-400 animate-pulse' : 'bg-white/30' }}"></span>
                        {{ $barber->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>

                {{-- Especialidades + info --}}
                <div class="flex-1 p-8">
                    <div class="mb-5">
                        <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Especialidades</p>
                        @if($barber->especialidades)
                            <div class="flex flex-wrap gap-2">
                                @foreach(explode(',', $barber->especialidades) as $esp)
                                    <span class="text-[10px] font-black border border-gold/20 bg-gold/8 text-gold rounded-full px-3 py-1 uppercase tracking-wider">
                                        {{ trim($esp) }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="text-sm text-muted italic">Sin especialidades registradas</p>
                        @endif
                    </div>
                    @if($barber->descripcion)
                        <div>
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-2">Descripción</p>
                            <p class="text-sm text-white/70 leading-relaxed">{{ $barber->descripcion }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        {{-- ── KPI CARDS ─────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach([
                ['label'=>'Citas Hoy',     'val'=>$stats['citas_hoy'],       'color'=>'blue'],
                ['label'=>'Citas Mes',     'val'=>$stats['citas_mes'],       'color'=>'emerald'],
                ['label'=>'Canceladas Mes','val'=>$stats['canceladas_mes'],  'color'=>'red'],
                ['label'=>'Histórico',     'val'=>$stats['total_historico'], 'color'=>'purple'],
                ['label'=>'Ingresos Mes',  'val'=>'$'.number_format($stats['ingresos_mes'],0), 'color'=>'gold'],
            ] as $kpi)
                <div class="rounded-2xl border {{ $kpi['color'] === 'gold' ? 'border-gold/20 bg-gold/5' : 'border-'.$kpi['color'].'-500/20 bg-'.$kpi['color'].'-500/5' }} p-4 text-center">
                    <p class="text-[10px] font-black uppercase tracking-wider {{ $kpi['color'] === 'gold' ? 'text-gold/70' : 'text-'.$kpi['color'].'-400/70' }} mb-1">{{ $kpi['label'] }}</p>
                    <p class="text-2xl font-black {{ $kpi['color'] === 'gold' ? 'text-gold' : 'text-'.$kpi['color'].'-400' }}">{{ $kpi['val'] }}</p>
                </div>
            @endforeach
        </section>

        {{-- ── CHARTS + TOP SERVICIOS ──────────────────────── --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Chart: actividad 28 días --}}
            <div class="lg:col-span-2 ui-card-premium p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-black text-white uppercase tracking-widest">Actividad Últimas 4 Semanas</h3>
                    <span class="text-[10px] font-bold text-muted uppercase">Citas completadas</span>
                </div>
                <div class="h-[200px]">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>

            {{-- Top servicios --}}
            <div class="ui-card-premium p-6">
                <h3 class="text-sm font-black text-white uppercase tracking-widest mb-6">Top Servicios</h3>
                @if($topServices->isEmpty())
                    <p class="text-sm text-muted italic text-center py-8">Sin datos</p>
                @else
                    <div class="space-y-4">
                        @foreach($topServices as $svc)
                            @php $max = $topServices->first()->total; $pct = $max > 0 ? ($svc->total / $max) * 100 : 0; @endphp
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <span class="text-xs font-bold text-white truncate flex-1 pr-3">{{ $svc->service?->nombre ?? '—' }}</span>
                                    <span class="text-xs font-black text-gold shrink-0">{{ $svc->total }}</span>
                                </div>
                                <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-gold to-gold-dim rounded-full transition-all duration-700" style="width:{{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ── CITAS RECIENTES ──────────────────────────────── --}}
        <section class="ui-card-premium p-6 sm:p-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-sm font-black text-white uppercase tracking-widest">Citas Recientes</h3>
                <span class="text-[10px] font-bold text-muted uppercase">Últimas {{ $recentAppointments->count() }}</span>
            </div>

            @if($recentAppointments->isEmpty())
                <div class="py-12 text-center border border-dashed border-white/10 rounded-2xl">
                    <p class="text-sm font-bold text-muted">Sin citas registradas</p>
                </div>
            @else
                <div class="hidden md:block ui-table-container">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Servicio</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentAppointments as $appt)
                                @php
                                    $statusCfg = [
                                        'completada' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/25',
                                        'pendiente'  => 'bg-amber-500/15 text-amber-300 border-amber-500/25',
                                        'cancelada'  => 'bg-red-500/15 text-red-400 border-red-500/25',
                                    ];
                                    $pill = $statusCfg[$appt->estado] ?? 'bg-white/8 text-white/50 border-white/10';
                                @endphp
                                <tr>
                                    <td>
                                        <p class="font-bold text-white text-sm">{{ \Carbon\Carbon::parse($appt->fecha)->format('d M, Y') }}</p>
                                        <p class="text-[10px] text-muted">{{ substr($appt->hora_inicio, 0, 5) }}</p>
                                    </td>
                                    <td class="text-white text-sm font-bold">{{ $appt->client?->user?->name ?? '—' }}</td>
                                    <td class="text-muted text-sm">{{ $appt->service?->nombre ?? '—' }}</td>
                                    <td>
                                        <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-[9px] font-black uppercase tracking-widest {{ $pill }}">
                                            {{ $appt->estado }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile --}}
                <div class="space-y-2 md:hidden">
                    @foreach($recentAppointments as $appt)
                        <div class="flex items-center gap-3 p-3 rounded-xl border border-white/5">
                            <div class="text-center w-10 shrink-0">
                                <p class="text-sm font-black text-white">{{ \Carbon\Carbon::parse($appt->fecha)->format('d') }}</p>
                                <p class="text-[9px] text-muted">{{ \Carbon\Carbon::parse($appt->fecha)->format('M') }}</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-white truncate">{{ $appt->client?->user?->name ?? '—' }}</p>
                                <p class="text-[9px] text-muted truncate">{{ $appt->service?->nombre ?? '—' }}</p>
                            </div>
                            <span class="text-[9px] font-black border rounded-full px-2 py-0.5 shrink-0 {{ $statusCfg[$appt->estado] ?? 'bg-white/8 text-white/50 border-white/10' }}">
                                {{ $appt->estado }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('activityChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: @json($weeklyChart->pluck('label')),
                    datasets: [{
                        data: @json($weeklyChart->pluck('count')),
                        backgroundColor: 'rgba(212,175,55,0.6)',
                        hoverBackgroundColor: '#d4af37',
                        borderRadius: 6,
                        barThickness: 10,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color:'#737373', font:{size:9}, maxRotation:45 }, grid: { display:false } },
                        y: { ticks: { color:'#737373', font:{size:10}, stepSize:1 }, grid: { color:'rgba(255,255,255,0.03)' }, beginAtZero:true }
                    }
                }
            });
        }
    </script>
</x-app-layout>
