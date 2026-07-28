<x-app-layout>
    <x-slot name="header">
        @php $rolLabels = ['administrador' => 'Administrativo', 'recepcionista' => 'Operativo', 'barbero' => 'Profesional', 'cliente' => 'Personal']; @endphp
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-gold/70 mb-1">UrbanBlade · Analítica</p>
                <h2 class="text-xl font-black text-white uppercase tracking-tight">Centro de análisis <span class="text-gold">{{ $rolLabels[$rolLabel] ?? 'Personal' }}</span></h2>
                <p class="text-[11px] text-white/50 mt-1">Resultados reales, explicados para tomar mejores decisiones.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="text-[10px] font-black uppercase tracking-widest text-white/50 hover:text-gold transition-colors">← Volver al panel</a>
        </div>
    </x-slot>

    @php
        $unidades = [
            'I' => ['titulo' => 'Resumen ejecutivo', 'subtitulo' => 'El pulso del negocio', 'activo' => 'border-sky-400/50 bg-sky-500/[0.10] text-white', 'acento' => 'text-sky-400'],
            'II' => ['titulo' => 'Operación y barberos', 'subtitulo' => 'Agenda, ventas e inventario', 'activo' => 'border-emerald-400/50 bg-emerald-500/[0.10] text-white', 'acento' => 'text-emerald-400'],
            'III' => ['titulo' => 'Predicción y alertas', 'subtitulo' => 'Demanda y cancelaciones', 'activo' => 'border-amber-400/50 bg-amber-500/[0.10] text-white', 'acento' => 'text-amber-400'],
            'IV' => ['titulo' => 'Clientes y fidelización', 'subtitulo' => 'Valor, riesgo y servicios', 'activo' => 'border-violet-400/50 bg-violet-500/[0.10] text-white', 'acento' => 'text-violet-400'],
            'V' => ['titulo' => 'Explorar gráficas', 'subtitulo' => 'Tendencias y comparativas', 'activo' => 'border-gold/50 bg-gold/[0.10] text-white', 'acento' => 'text-gold'],
        ];
        $visibles = collect(['I', 'II', 'III', 'IV'])->filter(fn ($unidad) => ($porUnidad ?? collect())->has($unidad));
        if (($visualizaciones ?? collect())->isNotEmpty()) $visibles->push('V');
        $introducciones = [
            'I' => 'Una vista rápida de los indicadores que más importan para decidir: ingresos, agenda, clientes y alertas.',
            'II' => 'Antes de decidir, los datos se revisan, limpian y organizan. Aquí ves la salud operativa actual de la barbería y sus procesos.',
            'III' => 'El sistema aprende del historial para anticipar escenarios. Úsalo como apoyo para planear, no como una garantía absoluta.',
            'IV' => 'Aquí se descubren grupos y relaciones sin decirle previamente a la computadora qué debe buscar: oportunidades que no saltan a la vista.',
            'V' => 'Explora las gráficas. Pasa el cursor sobre cada punto para ver el detalle y detectar tendencias.',
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
        $kpiStyles = [
            'gold' => ['borde' => 'border-gold/20', 'fondo' => 'bg-gold/[0.06]', 'texto' => 'text-gold'],
            'info' => ['borde' => 'border-sky-400/20', 'fondo' => 'bg-sky-500/[0.06]', 'texto' => 'text-sky-300'],
            'success' => ['borde' => 'border-emerald-400/20', 'fondo' => 'bg-emerald-500/[0.06]', 'texto' => 'text-emerald-300'],
            'warning' => ['borde' => 'border-amber-400/20', 'fondo' => 'bg-amber-500/[0.06]', 'texto' => 'text-amber-300'],
            'danger' => ['borde' => 'border-rose-400/20', 'fondo' => 'bg-rose-500/[0.06]', 'texto' => 'text-rose-300'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8" x-data="{ tab: '{{ $visibles->first() ?? 'I' }}', selectTab(value) { this.tab = value; setTimeout(() => window.dispatchEvent(new Event('resize')), 80); } }">
        @if($insights->isEmpty())
            <div class="rounded-3xl border border-white/[0.08] bg-[#111] p-10 text-center">
                <p class="text-sm font-black text-white uppercase">Aún no hay resultados disponibles</p>
                <p class="text-xs text-white/45 mt-2">El sistema actualizará este espacio cuando termine su siguiente revisión.</p>
            </div>
        @else
            <section class="rounded-3xl border border-gold/15 bg-gradient-to-br from-gold/[0.09] via-[#111] to-[#111] p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center gap-5">
                    <div class="h-12 w-12 rounded-2xl bg-gold/15 text-gold flex items-center justify-center shrink-0">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 15l3-3 3 2 4-6"/></svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gold">Tu recorrido de datos</p>
                        <p class="text-sm text-white/65 mt-1">Tus datos se convierten en hallazgos, alertas y recomendaciones claras. Las gráficas te ayudan a interpretar cada resultado sin conocimientos técnicos.</p>
                    </div>
                    <div class="text-left lg:text-right">
                        <p class="text-2xl font-black text-white">{{ $insights->count() }}</p>
                        <p class="text-[9px] font-black uppercase tracking-widest text-white/40">hallazgos para tu rol</p>
                    </div>
                </div>
            </section>

            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.22em] text-white/45">Resumen del negocio</p>
                    <p class="text-xs text-white/40 mt-1">Indicadores calculados con la información más reciente disponible.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-xl border border-white/[0.08] bg-white/[0.03] px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white/45">
                        {{ $ultimaActualizacion ? 'Actualizado '.$ultimaActualizacion->format('d/m/Y H:i') : 'Último cálculo disponible' }}
                    </span>
                    <a href="{{ route('analytics.index', ['actualizar' => 1]) }}" class="inline-flex items-center gap-2 rounded-xl bg-gold px-3 py-2 text-[9px] font-black uppercase tracking-widest text-black hover:bg-[#f0cc55] transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0017.5 17.5L20 15M18.4 9A7 7 0 006.5 6.5L4 9"/></svg>
                        Actualizar
                    </a>
                    @if($rolLabel === 'administrador')
                        <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'pdf']) }}" class="rounded-xl border border-white/[0.09] bg-white/[0.03] px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white/60 hover:border-gold/35 hover:text-gold transition-colors">Exportar PDF</a>
                        <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'excel']) }}" class="rounded-xl border border-white/[0.09] bg-white/[0.03] px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white/60 hover:border-gold/35 hover:text-gold transition-colors">Excel</a>
                    @endif
                </div>
            </div>

            @if($kpis->isNotEmpty())
                <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-7" aria-label="Indicadores principales">
                    @foreach($kpis as $kpi)
                        @php $style = $kpiStyles[$kpi['tone']] ?? $kpiStyles['gold']; @endphp
                        <article class="rounded-2xl border {{ $style['borde'] }} {{ $style['fondo'] }} p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[0_16px_35px_rgba(0,0,0,.18)]">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-[9px] font-black uppercase tracking-widest {{ $style['texto'] }}">{{ $kpi['label'] }}</p>
                                <span class="h-1.5 w-1.5 rounded-full bg-current {{ $style['texto'] }} opacity-80"></span>
                            </div>
                            <p class="text-xl font-black text-white mt-3 leading-tight">{{ $kpi['value'] }}</p>
                            <p class="text-[10px] text-white/40 mt-2">{{ $kpi['detail'] }}</p>
                        </article>
                    @endforeach
                </section>
            @endif

            <nav class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-2 mb-6" aria-label="Secciones de analítica">
                @foreach($visibles as $unidad)
                    @php $meta = $unidades[$unidad]; @endphp
                    <button @click="selectTab('{{ $unidad }}')" :class="tab === '{{ $unidad }}' ? '{{ $meta['activo'] }}' : 'border-white/[0.07] bg-white/[0.025] text-white/55 hover:bg-white/[0.05]'" class="text-left rounded-2xl border p-4 transition-all">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                            <p class="text-sm font-black uppercase {{ $meta['acento'] }}">{{ $meta['titulo'] }}</p>
                        </div>
                        <p class="text-[10px] mt-1 leading-snug opacity-60">{{ $meta['subtitulo'] }}</p>
                    </button>
                @endforeach
            </nav>

            <div class="flex flex-wrap items-center gap-2 mb-6 rounded-2xl border border-white/[0.07] bg-[#111] p-3">
                <span class="text-[9px] font-black uppercase tracking-widest text-white/35 mr-1">Accesos rápidos</span>
                @foreach([['II','Limpieza'],['III','Cancelaciones'],['IV','Clientes'],['V','Gráficas']] as $accion)
                    @if($visibles->contains($accion[0]))
                        <button @click="selectTab('{{ $accion[0] }}')" class="rounded-xl border border-white/[0.08] bg-white/[0.03] px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white/55 hover:border-gold/35 hover:text-gold transition-all">{{ $accion[1] }}</button>
                    @endif
                @endforeach
            </div>

            @foreach($visibles as $unidad)
                @php
                    $meta = $unidades[$unidad];
                    $unidadInsights = $unidad === 'V' ? collect() : ($porUnidad[$unidad] ?? collect());
                    $graficas = $unidad === 'V' ? ($visualizaciones ?? collect()) : collect();
                @endphp
                <section x-show="tab === '{{ $unidad }}'" x-cloak class="space-y-5">
                    <div class="rounded-2xl border border-white/[0.07] bg-[#111] p-5 flex gap-4 items-start">
                        <span class="h-2.5 w-2.5 rounded-full bg-gold mt-2 shrink-0 shadow-[0_0_14px_rgba(212,175,55,.45)]"></span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest {{ $meta['acento'] }} mb-1">{{ $meta['titulo'] }}</p>
                            <p class="text-sm text-white/65 leading-relaxed">{{ $introducciones[$unidad] }}</p>
                        </div>
                    </div>

                    @if($unidad === 'I')
                        <x-analytics-insights :insights="$summaryInsights" titulo="Lecturas clave" :showCharts="true" idPrefix="summary-chart" />
                    @elseif($unidad !== 'V')
                        <x-analytics-insights :insights="$unidadInsights" titulo="Hallazgos relevantes" :showCharts="true" idPrefix="unit-chart-{{ $unidad }}" />
                    @else
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                            @forelse($graficas as $insight)
                                <article class="self-start rounded-3xl border border-white/[0.07] bg-[#111] p-5 flex flex-col h-fit min-h-[360px] transition-all duration-300 hover:-translate-y-1 hover:shadow-[0_18px_45px_rgba(0,0,0,.22)]">
                                    <div class="flex items-start justify-between gap-4 mb-4">
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-widest text-gold/70">Visualización · {{ $insight->titulo }}</p>
                                            <p class="text-lg font-black text-white mt-1">{{ $insight->valor_destacado }}</p>
                                        </div>
                                        <span class="h-2 w-2 rounded-full bg-gold mt-2"></span>
                                    </div>
                                    <div class="h-60"><canvas id="chart-{{ $loop->index }}"></canvas></div>
                                    <p class="mt-4 pt-4 border-t border-white/[0.06] text-[11px] leading-relaxed text-white/55">{{ $insight->mensaje }}</p>
                                </article>
                            @empty
                                <div class="lg:col-span-2 rounded-2xl border border-dashed border-white/[0.1] p-10 text-center text-sm text-white/45">Este rol no tiene gráficas disponibles todavía.</div>
                            @endforelse
                        </div>
                    @endif
                </section>
            @endforeach
        @endif
    </div>

    @if($tieneGraficas)
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const palette = ['#d4af37', '#38bdf8', '#34d399', '#a78bfa', '#f59e0b', '#fb7185', '#22d3ee'];
                const scale = { ticks: { color: 'rgba(255,255,255,.38)', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.06)' } };
                @foreach($visualizaciones as $insight)
                    @php $graph = $insight->grafica; $tipoVisual = $tiposVisuales[$insight->tipo] ?? ($graph['tipo'] ?? 'bar'); @endphp
                    (() => {
                        const graph = @json($graph);
                        const canvas = document.getElementById('chart-{{ $loop->index }}');
                        if (!canvas || !graph) return;
                        const type = '{{ $tipoVisual }}';
                        const isLine = type === 'line';
                        const horizontal = type === 'bar' && (graph.labels || []).length > 6;
                        new Chart(canvas, {
                            type,
                            data: { labels: graph.labels || [], datasets: [{ data: graph.valores || [], borderColor: '#d4af37', backgroundColor: isLine ? 'rgba(212,175,55,.12)' : (graph.valores || []).map((_, i) => palette[i % palette.length] + 'cc'), fill: isLine, tension: .38, borderWidth: isLine ? 2.5 : 0, borderRadius: type === 'bar' ? 8 : 0, pointRadius: isLine ? 3 : 0, pointBackgroundColor: '#d4af37' }] },
                            options: { indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false, animation: { duration: 900, easing: 'easeOutQuart' }, plugins: { legend: { display: ['doughnut','polarArea'].includes(type), position: 'bottom', labels: { color: 'rgba(255,255,255,.55)', usePointStyle: true, padding: 14 } }, tooltip: { backgroundColor: '#111', borderColor: 'rgba(212,175,55,.35)', borderWidth: 1, titleColor: '#d4af37', bodyColor: '#fff' } }, cutout: type === 'doughnut' ? '68%' : undefined, scales: ['doughnut','polarArea'].includes(type) ? {} : { x: { ...scale, beginAtZero: horizontal }, y: { ...scale, beginAtZero: !horizontal } } }
                        });
                    })();
                @endforeach
            });
        </script>
    @endif
</x-app-layout>
