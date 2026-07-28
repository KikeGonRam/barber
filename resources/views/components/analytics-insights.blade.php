{{--
    Tarjetas de analítica en lenguaje natural, reutilizables en los 4
    dashboards por rol.

    Uso:
        <x-analytics-insights :insights="$sparkInsights" titulo="Analítica avanzada" />

    $insights: una Collection/array de AnalyticsInsight (o arrays con las
    mismas claves: titulo, valor_destacado, mensaje, color). Si viene vacía,
    el componente no dibuja nada (evita una sección vacía en pantalla).

    El color de cada tarjeta viene calculado por Spark en el propio dato
    (campo `color`), no aquí — este componente solo traduce ese nombre de
    color a las clases de Tailwind correspondientes, para que la paleta se
    mantenga centralizada en un solo lugar y todos los dashboards se vean
    consistentes entre sí.
--}}
@props(['insights' => [], 'titulo' => 'Analítica avanzada', 'showCharts' => false, 'idPrefix' => 'insight-chart'])

@php
    // Mapa de color -> clases Tailwind. Si Spark manda un color que no está
    // en este mapa (por un typo o un valor nuevo no contemplado), se usa
    // 'gold' como respaldo seguro en vez de romper el render.
    $paleta = [
        'gold'    => ['border' => 'border-gold/15',        'bg' => 'bg-gold/[0.03]',        'texto' => 'text-gold/70'],
        'success' => ['border' => 'border-emerald-500/20', 'bg' => 'bg-emerald-500/[0.04]', 'texto' => 'text-emerald-400/80'],
        'warning' => ['border' => 'border-amber-500/20',   'bg' => 'bg-amber-500/[0.04]',   'texto' => 'text-amber-400/80'],
        'danger'  => ['border' => 'border-rose-500/20',    'bg' => 'bg-rose-500/[0.04]',    'texto' => 'text-rose-400/80'],
        'info'    => ['border' => 'border-sky-500/20',     'bg' => 'bg-sky-500/[0.04]',     'texto' => 'text-sky-400/80'],
    ];
    $tiposVisuales = [
        'resumen_ejecutivo' => 'line', 'calidad_datos_etl' => 'doughnut',
        'demanda_horas_pico' => 'line', 'demanda_horas_pico_propia' => 'line',
        'clientes_en_riesgo' => 'doughnut', 'segmentacion_clientes' => 'doughnut',
        'perfil_citas_premium' => 'doughnut', 'fidelizacion_ratio' => 'bar',
        'utilizacion_equipo' => 'bar', 'engagement_muro_top' => 'bar',
        'tienda_pedidos' => 'bar', 'pca_factores' => 'polarArea',
        'clasificacion_cancelacion' => 'line', 'alertas_cancelacion' => 'bar',
        'matriz_resultados_cancelacion' => 'bar',
    ];
@endphp

@if(!empty($insights) && count($insights) > 0)
    <section aria-label="{{ $titulo }}">
        <div class="flex items-center gap-2 mb-3 px-1">
            <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0013 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            <h3 class="text-[11px] font-black uppercase tracking-widest text-gold">{{ $titulo }}</h3>
            <span class="text-[9px] text-muted">· UrbanBlade</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 items-start gap-4">
            @foreach($insights as $insight)
                @php
                    $c = $paleta[$insight['color'] ?? $insight->color ?? 'gold'] ?? $paleta['gold'];
                    $tit  = $insight['titulo'] ?? $insight->titulo;
                    $dato = $insight['valor_destacado'] ?? $insight->valor_destacado;
                    $msg  = $insight['mensaje'] ?? $insight->mensaje;
                    $grafica = $insight['grafica'] ?? $insight->grafica ?? null;
                    $chartId = $idPrefix.'-'.($insight['tipo'] ?? $insight->tipo ?? $loop->index).'-'.$loop->index;
                    $tipoInsight = $insight['tipo'] ?? $insight->tipo ?? '';
                    $tipoVisual = $tiposVisuales[$tipoInsight] ?? ($grafica['tipo'] ?? 'bar');
                @endphp
                <article class="self-start h-fit rounded-3xl border {{ $c['border'] }} {{ $c['bg'] }} p-5 transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(0,0,0,.22)]">
                    <p class="text-[9px] font-black uppercase tracking-widest {{ $c['texto'] }}">{{ $tit }}</p>
                    <div class="flex items-end justify-between gap-3 mt-1">
                        <p class="text-2xl font-black text-white leading-tight">{{ $dato }}</p>
                        @if($grafica && $showCharts)
                            <span class="text-[9px] text-white/35 uppercase tracking-widest">Detalle</span>
                        @endif
                    </div>
                    @if($grafica && $showCharts)
                        <div class="h-44 mt-4"><canvas id="{{ $chartId }}"></canvas></div>
                    @endif
                    <p class="text-[11px] text-muted mt-3 leading-relaxed">{{ $msg }}</p>
                </article>
            @endforeach
        </div>
    </section>
    @if($showCharts && collect($insights)->contains(fn ($i) => !empty($i['grafica'] ?? $i->grafica ?? null)))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const colors = ['#d4af37','#38bdf8','#34d399','#a78bfa','#f59e0b','#fb7185','#22d3ee'];
                const axis = { ticks: { color: 'rgba(255,255,255,.36)', font: { size: 9 } }, grid: { color: 'rgba(255,255,255,.055)' } };
                @foreach($insights as $insight)
                    @php $graph = $insight['grafica'] ?? $insight->grafica ?? null; $tipoInsight = $insight['tipo'] ?? $insight->tipo ?? ''; $tipoVisual = $tiposVisuales[$tipoInsight] ?? ($graph['tipo'] ?? 'bar'); $chartId = $idPrefix.'-'.$tipoInsight.'-'.$loop->index; @endphp
                    @if($graph)
                        (() => {
                            const graph = @json($graph); const canvas = document.getElementById('{{ $chartId }}');
                            if (!canvas) return;
                            const type = '{{ $tipoVisual }}'; const line = type === 'line'; const horizontal = type === 'bar' && (graph.labels || []).length > 6;
                            new Chart(canvas, { type, data: { labels: graph.labels || [], datasets: [{ data: graph.valores || [], backgroundColor: line ? 'rgba(212,175,55,.14)' : (graph.valores || []).map((_,i) => colors[i % colors.length] + 'cc'), borderColor: '#d4af37', borderWidth: line ? 2 : 0, borderRadius: type === 'bar' ? 7 : 0, fill: line, tension: .4, pointRadius: line ? 2 : 0 }] }, options: { indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false, animation: { duration: 900, easing: 'easeOutQuart' }, plugins: { legend: { display: ['doughnut','polarArea'].includes(type), position: 'bottom', labels: { color: 'rgba(255,255,255,.5)', usePointStyle: true, boxWidth: 7, padding: 8, font: { size: 9 } } }, tooltip: { backgroundColor: '#111', borderColor: 'rgba(212,175,55,.35)', borderWidth: 1, titleColor: '#d4af37', bodyColor: '#fff', padding: 9 } }, cutout: type === 'doughnut' ? '66%' : undefined, scales: ['doughnut','polarArea'].includes(type) ? {} : { x: { ...axis, beginAtZero: horizontal }, y: { ...axis, beginAtZero: !horizontal } } } });
                        })();
                    @endif
                @endforeach
            });
        </script>
    @endif
@endif
