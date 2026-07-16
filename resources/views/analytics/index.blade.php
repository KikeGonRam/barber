<x-app-layout>
    <x-slot name="header">
        @php
            $rolLabels = ['administrador' => 'Administrativo', 'recepcionista' => 'Operativo', 'barbero' => 'Profesional', 'cliente' => 'Personal'];
        @endphp
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50 mb-1">UrbanBlade · Analítica</p>
                <h2 class="text-xl font-black text-white uppercase tracking-tight">
                    Panel <span class="text-gold">{{ $rolLabels[$rolLabel] ?? 'Analítica' }}</span>
                </h2>
                <p class="text-[10px] text-white/50 font-bold mt-0.5 uppercase tracking-wider">Calculado por UrbanBlade Analytics · se actualiza automáticamente</p>
            </div>
        </div>
    </x-slot>

    {{--
        Página de Analítica — separada del dashboard principal a propósito:
        el dashboard es para operar el día a día (agenda, cobros, pedidos),
        esta página es para ENTENDER patrones de negocio con más calma,
        organizada por pestañas (una por "unidad" de análisis) para que no
        se sienta como una sola lista larga de tarjetas.

        $porUnidad viene ya agrupado y filtrado por rol desde el controlador
        (AnalyticsController) — aquí solo se decide CÓMO se ve, no qué se ve.
    --}}
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8" x-data="{ tab: '{{ $porUnidad->keys()->first() ?? 'I' }}' }">

        @if($insights->isEmpty())
            <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-10 text-center">
                <p class="text-sm font-black text-white uppercase">Todavía no hay analítica disponible</p>
                <p class="text-xs text-white/40 mt-2">El equipo técnico corre el análisis periódicamente — vuelve a revisar más tarde.</p>
            </div>
        @else
            {{-- ── PESTAÑAS ─────────────────────────────────────────── --}}
            @php
                $tabLabels = [
                    'I'   => ['label' => '¿Qué es esto?',        'icon' => 'info'],
                    'II'  => ['label' => 'Operación del negocio', 'icon' => 'ops'],
                    'III' => ['label' => 'Demanda y clientes',    'icon' => 'demand'],
                    'IV'  => ['label' => 'Segmentos y patrones',  'icon' => 'segments'],
                    'V'   => ['label' => 'Resumen',               'icon' => 'summary'],
                ];
                $unidadesOrdenadas = collect(['I', 'II', 'III', 'IV', 'V'])->filter(fn ($u) => $porUnidad->has($u));
            @endphp

            <div class="flex flex-wrap gap-2 mb-6 border-b border-white/[0.06] pb-3">
                @foreach($unidadesOrdenadas as $u)
                    <button
                        @click="tab = '{{ $u }}'"
                        :class="tab === '{{ $u }}' ? 'bg-gold text-black' : 'bg-white/[0.04] text-white/50 hover:text-white'"
                        class="px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all"
                    >
                        {{ $tabLabels[$u]['label'] ?? "Unidad $u" }}
                        <span class="opacity-60 ml-1">({{ $porUnidad[$u]->count() }})</span>
                    </button>
                @endforeach
            </div>

            {{-- ── CONTENIDO POR PESTAÑA ────────────────────────────── --}}
            @foreach($unidadesOrdenadas as $u)
                <div x-show="tab === '{{ $u }}'" x-cloak class="space-y-6">

                    {{-- Tarjetas de esta unidad (mismo componente del dashboard) --}}
                    <x-analytics-insights :insights="$porUnidad[$u]" titulo="{{ $tabLabels[$u]['label'] ?? '' }}" />

                    {{-- Gráficas de esta unidad, si trae alguna --}}
                    @php $conGrafica = $porUnidad[$u]->filter(fn ($i) => !empty($i->grafica)); @endphp
                    @if($conGrafica->isNotEmpty())
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            @foreach($conGrafica as $i)
                                <div class="rounded-2xl border border-white/[0.06] bg-[#111] p-5">
                                    <p class="text-[9px] font-black uppercase tracking-[0.22em] text-white/50 mb-1">{{ $i->titulo }}</p>
                                    <div class="h-64">
                                        <canvas id="chart-{{ $i->tipo }}-{{ $loop->parent->index }}-{{ $loop->index }}"></canvas>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- ── CHART.JS: una gráfica por cada insight que trae `grafica` ── --}}
    @if($tieneGraficas)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Chart.defaults.font.family = "'Figtree', sans-serif";
                Chart.defaults.color = 'rgba(255,255,255,0.3)';
                Chart.defaults.font.weight = 'bold';

                const scale = {
                    ticks: { color: 'rgba(255,255,255,0.25)', font: { size: 10 } },
                    grid:  { color: 'rgba(255,255,255,0.04)', drawBorder: false },
                };
                const paleta = ['#d4af37', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#a78bfa'];

                @foreach($unidadesOrdenadas as $u)
                    @php $conGrafica = $porUnidad[$u]->filter(fn ($i) => !empty($i->grafica)); @endphp
                    @foreach($conGrafica as $i)
                        @php $g = $i->grafica; @endphp
                        (function () {
                            const el = document.getElementById('chart-{{ $i->tipo }}-{{ $loop->parent->index }}-{{ $loop->index }}');
                            if (!el) return;
                            const tipoGrafica = '{{ $g['tipo'] }}';
                            const bg = tipoGrafica === 'doughnut' ? paleta
                                     : tipoGrafica === 'line' ? 'rgba(212,175,55,0.08)'
                                     : 'rgba(212,175,55,0.75)';
                            new Chart(el, {
                                type: tipoGrafica,
                                data: {
                                    labels: @json($g['labels'] ?? []),
                                    datasets: [{
                                        data: @json($g['valores'] ?? []),
                                        backgroundColor: bg,
                                        borderColor: tipoGrafica === 'line' ? '#d4af37' : undefined,
                                        fill: tipoGrafica === 'line',
                                        tension: 0.4,
                                        borderRadius: tipoGrafica === 'bar' ? 6 : 0,
                                        borderWidth: tipoGrafica === 'doughnut' ? 0 : 2,
                                        hoverOffset: tipoGrafica === 'doughnut' ? 10 : 0,
                                    }],
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: '{{ $g['tipo'] }}' === 'doughnut',
                                            position: 'bottom',
                                            labels: { color: 'rgba(255,255,255,0.35)', usePointStyle: true, padding: 12, font: { size: 10, weight: 'bold' } },
                                        },
                                    },
                                    cutout: '{{ $g['tipo'] }}' === 'doughnut' ? '72%' : undefined,
                                    scales: '{{ $g['tipo'] }}' === 'doughnut' ? {} : { y: scale, x: scale },
                                },
                            });
                        })();
                    @endforeach
                @endforeach
            });
        </script>
    @endif
</x-app-layout>
