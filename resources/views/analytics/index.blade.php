<x-app-layout>
    <x-slot name="header">
        @php $rolLabels = ['administrador' => 'Administrativo', 'recepcionista' => 'Operativo', 'barbero' => 'Profesional', 'cliente' => 'Personal']; @endphp
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-1 text-[10px] font-black uppercase tracking-[0.28em] text-gold/75">UrbanBlade · Inteligencia del negocio</p>
                <h2 class="text-xl font-black uppercase tracking-tight text-white">Centro de análisis <span class="text-gold">{{ $rolLabels[$rolLabel] ?? 'Personal' }}</span></h2>
                <p class="mt-1 text-[12px] text-white/50">Indicadores, predicciones y recomendaciones listos para decidir.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-white/50 transition-colors hover:text-gold">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06L5.56 9.25h10.69A.75.75 0 0 1 17 10Z" clip-rule="evenodd" /></svg>
                Volver al panel
            </a>
        </div>
    </x-slot>

    @php
        $visibles = collect($secciones ?? [])->keys()->filter(fn ($seccion) => ($porSeccion[$seccion] ?? collect())->isNotEmpty());
        $tieneMiniGraficas = $kpis->contains(fn ($kpi) => ! empty($kpi['graph']));
        $kpiStyles = [
            'gold' => ['border' => 'border-gold/20', 'bg' => 'bg-gold/[0.055]', 'text' => 'text-gold', 'bar' => 'bg-gold', 'hex' => '#d4af37'],
            'info' => ['border' => 'border-sky-400/20', 'bg' => 'bg-sky-500/[0.055]', 'text' => 'text-sky-300', 'bar' => 'bg-sky-400', 'hex' => '#38bdf8'],
            'success' => ['border' => 'border-emerald-400/20', 'bg' => 'bg-emerald-500/[0.055]', 'text' => 'text-emerald-300', 'bar' => 'bg-emerald-400', 'hex' => '#34d399'],
            'warning' => ['border' => 'border-amber-400/20', 'bg' => 'bg-amber-500/[0.055]', 'text' => 'text-amber-300', 'bar' => 'bg-amber-400', 'hex' => '#f59e0b'],
            'danger' => ['border' => 'border-rose-400/20', 'bg' => 'bg-rose-500/[0.055]', 'text' => 'text-rose-300', 'bar' => 'bg-rose-400', 'hex' => '#fb7185'],
        ];
        $miniChartTypes = [
            'segmentacion_clientes' => 'doughnut',
            'clientes_en_riesgo' => 'doughnut',
            'perfil_citas_premium' => 'doughnut',
            'calidad_datos_etl' => 'doughnut',
            'utilizacion_equipo' => 'bar',
            'utilizacion_propia' => 'bar',
        ];
        $tabActiveStyles = [
            'resumen' => 'border-sky-400/50 bg-sky-500/[0.10] text-white',
            'operacion' => 'border-emerald-400/50 bg-emerald-500/[0.10] text-white',
            'clientes' => 'border-violet-400/50 bg-violet-500/[0.10] text-white',
            'prediccion' => 'border-amber-400/50 bg-amber-500/[0.10] text-white',
        ];
        $quickActions = [
            ['tab' => 'resumen', 'label' => 'Limpieza de datos', 'detail' => 'Calidad del registro', 'mode' => 'diagnostic'],
            ['tab' => 'prediccion', 'label' => 'Árbol de decisión', 'detail' => 'Factores de cancelación'],
            ['tab' => 'prediccion', 'label' => 'Matriz de resultados', 'detail' => 'Aciertos y falsas alarmas'],
            ['tab' => 'clientes', 'label' => 'Clientes y ventas', 'detail' => 'VIP, riesgo y add-ons'],
        ];
    @endphp

    <div
        class="font-analytics space-y-5"
        x-data="{
            tab: '{{ $visibles->first() ?? 'resumen' }}',
            diagnosticoOpen: false,
            selectTab(value) {
                this.tab = value;
                setTimeout(() => window.dispatchEvent(new Event('ub:analytics:resize')), 120);
            },
            openDiagnostic() {
                this.tab = 'resumen';
                this.diagnosticoOpen = true;
                setTimeout(() => {
                    document.getElementById('diagnostico-datos')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    window.dispatchEvent(new Event('ub:analytics:resize'));
                }, 140);
            }
        }"
    >
        @if($insights->isEmpty())
            <div class="rounded-[8px] border border-white/[0.08] bg-[#101010] p-10 text-center">
                <p class="text-sm font-black uppercase text-white">Aún no hay resultados disponibles</p>
                <p class="mt-2 text-xs text-white/45">El sistema actualizará este espacio cuando termine su siguiente revisión.</p>
            </div>
        @else
            <section class="rounded-[8px] border border-white/[0.08] bg-[#101010]/95 p-4 shadow-[0_22px_55px_rgba(0,0,0,.22)] sm:p-5">
                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-gold/25 bg-gold/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em] text-gold">Panel ejecutivo</span>
                            <span class="text-[11px] font-bold text-white/38">{{ $insights->count() }} hallazgos disponibles para este rol</span>
                        </div>
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-white/62">
                            Los resultados de Spark se muestran por prioridad administrativa: primero el pulso del negocio, después operación, clientes y predicción.
                        </p>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2 xl:min-w-[520px] xl:grid-cols-[140px_160px_auto]">
                        <label class="block">
                            <span class="mb-1 block text-[9px] font-black uppercase tracking-widest text-white/35">Rango</span>
                            <select class="h-9 w-full rounded-[8px] border-white/[0.08] bg-white/[0.035] px-2 text-[11px] font-bold text-white/70 focus:border-gold focus:ring-gold/25">
                                <option>Últimos 30 días</option>
                                <option>Últimos 90 días</option>
                                <option>Histórico</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-[9px] font-black uppercase tracking-widest text-white/35">Sucursal</span>
                            <select class="h-9 w-full rounded-[8px] border-white/[0.08] bg-white/[0.035] px-2 text-[11px] font-bold text-white/70 focus:border-gold focus:ring-gold/25">
                                <option>Sucursal principal</option>
                            </select>
                        </label>
                        <div class="flex items-end gap-2">
                            <a href="{{ route('analytics.index', ['actualizar' => 1]) }}" title="Actualizar resultados" class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] bg-gold text-black shadow-[0_10px_24px_rgba(212,175,55,.18)] transition hover:bg-[#f0cc55]">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5.6 15A7 7 0 0017.5 17.5L20 15M18.4 9A7 7 0 006.5 6.5L4 9"/></svg>
                            </a>
                            @if($rolLabel === 'administrador')
                                <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'pdf']) }}" title="Exportar PDF" class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-white/[0.08] bg-white/[0.035] text-white/60 transition hover:border-gold/35 hover:text-gold">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.621a1.5 1.5 0 0 0-.44-1.06l-4.12-4.122A1.5 1.5 0 0 0 11.378 2H4.5Zm6 1.5V7A1.5 1.5 0 0 0 12 8.5h3.5v8h-11v-13h6Z"/><path d="M6.5 11.25a.75.75 0 0 1 .75-.75h5.5a.75.75 0 0 1 0 1.5h-5.5a.75.75 0 0 1-.75-.75Zm0 3a.75.75 0 0 1 .75-.75h3.5a.75.75 0 0 1 0 1.5h-3.5a.75.75 0 0 1-.75-.75Z"/></svg>
                                </a>
                                <a href="{{ route('reports.export', ['type' => 'ingresos', 'format' => 'excel']) }}" title="Exportar Excel" class="inline-flex h-9 w-9 items-center justify-center rounded-[8px] border border-white/[0.08] bg-white/[0.035] text-white/60 transition hover:border-emerald-400/35 hover:text-emerald-300">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 3.5A1.5 1.5 0 0 1 5.5 2h7.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12A1.5 1.5 0 0 1 17 5.622V16.5a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 4 16.5v-13ZM6.6 7.8l2.1 2.2-2.1 2.2 1.1 1.05L9.8 11l2.1 2.25L13 12.2 10.9 10 13 7.8l-1.1-1.05L9.8 9 7.7 6.75 6.6 7.8Z"/></svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/[0.06] pt-3">
                    <span class="text-[10px] font-bold text-white/40">
                        {{ $ultimaActualizacion ? 'Última actualización: '.$ultimaActualizacion->format('d/m/Y H:i') : 'Último cálculo disponible' }}
                    </span>
                    <span class="hidden h-1 w-1 rounded-full bg-white/25 sm:inline-block"></span>
                    <span class="text-[10px] font-bold text-white/35">Vista optimizada para administración y toma de decisiones.</span>
                </div>
            </section>

            @if($kpis->isNotEmpty())
                <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5" aria-label="Indicadores principales">
                    @foreach($kpis as $kpi)
                        @php
                            $style = $kpiStyles[$kpi['tone']] ?? $kpiStyles['gold'];
                            $miniGraph = $kpi['graph'] ?? null;
                            $miniId = \Illuminate\Support\Str::slug('kpi-chart-'.$kpi['type'].'-'.$loop->index);
                            $miniType = $miniChartTypes[$kpi['type'] ?? ''] ?? data_get($miniGraph, 'tipo', 'line');
                            $miniConfig = [
                                'graph' => $miniGraph,
                                'type' => $miniType,
                                'tone' => $kpi['tone'],
                                'accent' => $style['hex'],
                                'mini' => true,
                                'insightType' => $kpi['type'],
                            ];
                            $progress = null;
                            if (preg_match('/([\d]+(?:[.,]\d+)?)\s*%/', (string) $kpi['value'], $matches)) {
                                $progress = max(3, min(100, (float) str_replace(',', '.', $matches[1])));
                            }
                        @endphp
                        <article class="overflow-hidden rounded-[8px] border {{ $style['border'] }} {{ $style['bg'] }} p-4 shadow-[0_16px_35px_rgba(0,0,0,.16)] transition duration-300 hover:-translate-y-0.5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-[9px] font-black uppercase tracking-[0.16em] {{ $style['text'] }}">{{ $kpi['label'] }}</p>
                                    <p class="mt-2 text-2xl font-black leading-none text-white">{{ $kpi['value'] }}</p>
                                </div>
                                <span class="h-2 w-2 rounded-full {{ $style['bar'] }} shadow-[0_0_14px_rgba(255,255,255,.12)]"></span>
                            </div>

                            @if($miniGraph)
                                <div class="mt-3 h-16">
                                    <canvas
                                        id="{{ $miniId }}"
                                        data-ub-analytics-chart
                                        data-chart-mini="true"
                                        data-chart-config="{{ $miniId }}-config"
                                        aria-label="{{ $kpi['label'] }}"
                                    ></canvas>
                                    <script id="{{ $miniId }}-config" type="application/json">
                                        {!! json_encode($miniConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                    </script>
                                </div>
                            @elseif($progress !== null)
                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
                                    <span class="block h-full rounded-full {{ $style['bar'] }}" style="width: {{ $progress }}%"></span>
                                </div>
                            @else
                                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/[0.07]">
                                    <span class="block h-full w-2/3 rounded-full {{ $style['bar'] }}"></span>
                                </div>
                            @endif

                            <p class="mt-3 text-[10px] leading-snug text-white/42">{{ $kpi['detail'] }}</p>
                        </article>
                    @endforeach
                </section>
            @endif

            <section class="rounded-[8px] border border-white/[0.08] bg-[#101010]/90 p-2">
                <nav class="grid grid-cols-1 gap-1 sm:grid-cols-2 xl:grid-cols-4" aria-label="Secciones de analítica">
                    @foreach($visibles as $seccion)
                        @php
                            $meta = $secciones[$seccion];
                            $activeStyle = $tabActiveStyles[$seccion] ?? 'border-gold/40 bg-gold/[0.10] text-white';
                        @endphp
                        <button
                            type="button"
                            @click="selectTab('{{ $seccion }}')"
                            :class="tab === '{{ $seccion }}' ? '{{ $activeStyle }} shadow-[0_10px_28px_rgba(0,0,0,.18)]' : 'border-transparent bg-transparent text-white/48 hover:bg-white/[0.045] hover:text-white/72'"
                            class="rounded-[8px] border px-4 py-3 text-left transition"
                        >
                            <span class="block text-[12px] font-black">{{ $meta['titulo'] }}</span>
                            <span class="mt-0.5 block text-[10px] font-bold opacity-55">{{ $meta['subtitulo'] }}</span>
                        </button>
                    @endforeach
                </nav>
            </section>

            <section class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-4" aria-label="Acciones de análisis">
                @foreach($quickActions as $accion)
                    @if($accion['mode'] ?? null)
                        <button type="button" @click="openDiagnostic()" class="rounded-[8px] border border-white/[0.08] bg-white/[0.025] px-4 py-3 text-left transition hover:border-gold/30 hover:bg-gold/[0.05]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-gold">{{ $accion['label'] }}</span>
                            <span class="mt-1 block text-[11px] text-white/42">{{ $accion['detail'] }}</span>
                        </button>
                    @elseif($visibles->contains($accion['tab']))
                        <button type="button" @click="selectTab('{{ $accion['tab'] }}')" class="rounded-[8px] border border-white/[0.08] bg-white/[0.025] px-4 py-3 text-left transition hover:border-gold/30 hover:bg-gold/[0.05]">
                            <span class="block text-[10px] font-black uppercase tracking-[0.16em] text-gold">{{ $accion['label'] }}</span>
                            <span class="mt-1 block text-[11px] text-white/42">{{ $accion['detail'] }}</span>
                        </button>
                    @endif
                @endforeach
            </section>

            @foreach($visibles as $seccion)
                @php
                    $meta = $secciones[$seccion];
                    $seccionInsights = $porSeccion[$seccion] ?? collect();
                @endphp
                <section x-show="tab === '{{ $seccion }}'" x-cloak class="space-y-4">
                    <div class="flex flex-col gap-2 border-b border-white/[0.07] pb-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $meta['acento'] }}">{{ $meta['titulo'] }}</p>
                            <p class="mt-1 max-w-3xl text-sm leading-relaxed text-white/58">{{ $meta['intro'] }}</p>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-[0.16em] text-white/35">{{ $seccionInsights->count() }} módulos</span>
                    </div>

                    <x-analytics-insights :insights="$seccionInsights" titulo="Hallazgos relevantes" :showCharts="true" idPrefix="section-chart-{{ $seccion }}" />

                    @if($seccion === 'resumen' && $diagnosticoInsights->isNotEmpty())
                        <details id="diagnostico-datos" :open="diagnosticoOpen" class="group rounded-[8px] border border-white/[0.08] bg-[#101010]/92 p-4">
                            <summary @click="diagnosticoOpen = !diagnosticoOpen" class="flex cursor-pointer list-none items-center justify-between gap-4 text-[10px] font-black uppercase tracking-[0.16em] text-white/60">
                                <span class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_14px_rgba(52,211,153,.35)]"></span>
                                    Diagnóstico de datos
                                </span>
                                <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </summary>
                            <div class="mt-5">
                                <x-analytics-insights :insights="$diagnosticoInsights" titulo="Calidad y limpieza" :showCharts="true" idPrefix="diagnostic-chart" />
                            </div>
                        </details>
                    @endif
                </section>
            @endforeach
        @endif
    </div>

    @if(($tieneGraficas ?? false) || ($tieneMiniGraficas ?? false))
        <script>
            (() => {
                const palette = ['#d4af37', '#38bdf8', '#34d399', '#a78bfa', '#f59e0b', '#fb7185', '#22d3ee', '#84cc16'];
                const instances = window.__ubAnalyticsCharts || {};
                window.__ubAnalyticsCharts = instances;

                const numberFormatter = new Intl.NumberFormat('es-MX');
                const textColor = 'rgba(255,255,255,.48)';
                const gridColor = 'rgba(255,255,255,.07)';

                const parseConfig = (canvas) => {
                    const node = document.getElementById(canvas.dataset.chartConfig);
                    if (!node) return null;
                    try { return JSON.parse(node.textContent); } catch (error) { return null; }
                };

                const toValues = (values) => (values || []).map((value) => {
                    const numeric = Number(String(value).replace(/,/g, ''));
                    return Number.isFinite(numeric) ? numeric : 0;
                });

                const shortLabel = (label, mini) => {
                    const text = String(label ?? '').replace(/\n/g, ' · ');
                    if (mini) return '';
                    return text.length > 28 ? `${text.slice(0, 26)}…` : text;
                };

                const bootCharts = () => {
                    if (!window.Chart) {
                        setTimeout(bootCharts, 80);
                        return;
                    }

                    window.Chart.defaults.font.family = "'Plus Jakarta Sans', 'Figtree', sans-serif";
                    window.Chart.defaults.color = textColor;

                    document.querySelectorAll('[data-ub-analytics-chart]').forEach((canvas) => {
                        const config = parseConfig(canvas);
                        if (!config) return;

                        if (instances[canvas.id]) {
                            instances[canvas.id].resize();
                            return;
                        }

                        const graph = config.graph || {};
                        const labels = graph.labels || [];
                        const values = toValues(graph.valores || []);
                        if (!values.length) return;

                        const mini = canvas.dataset.chartMini === 'true' || config.mini === true;
                        const requestedType = config.type === 'matrix' ? 'bar' : (config.type || graph.tipo || 'bar');
                        const longLabels = labels.length > 5 || labels.some((label) => String(label).length > 18);
                        const horizontal = requestedType === 'bar' && longLabels && !mini;
                        const accent = config.accent || '#d4af37';
                        const ctx = canvas.getContext('2d');
                        const gradient = ctx.createLinearGradient(0, 0, 0, canvas.parentElement?.clientHeight || 240);
                        gradient.addColorStop(0, `${accent}33`);
                        gradient.addColorStop(1, `${accent}05`);

                        const dataset = {
                            data: values,
                            label: 'Resultado',
                            backgroundColor: values.map((_, index) => `${palette[index % palette.length]}cc`),
                            borderColor: accent,
                            borderWidth: 0,
                            borderRadius: requestedType === 'bar' ? 8 : 0,
                            hoverBorderColor: '#ffffff',
                            hoverBorderWidth: requestedType === 'bar' ? 0 : 1,
                        };

                        if (requestedType === 'line') {
                            dataset.backgroundColor = gradient;
                            dataset.borderColor = accent;
                            dataset.borderWidth = mini ? 1.8 : 2.6;
                            dataset.fill = true;
                            dataset.tension = 0.42;
                            dataset.pointRadius = mini ? 0 : 3;
                            dataset.pointHoverRadius = 5;
                        }

                        if (requestedType === 'radar') {
                            dataset.backgroundColor = `${accent}24`;
                            dataset.borderColor = accent;
                            dataset.borderWidth = 2;
                            dataset.pointBackgroundColor = accent;
                            dataset.pointRadius = 2;
                        }

                        if (['doughnut', 'polarArea'].includes(requestedType)) {
                            dataset.borderColor = '#101010';
                            dataset.borderWidth = 2;
                            dataset.hoverOffset = mini ? 2 : 6;
                        }

                        const axes = {
                            x: {
                                display: !mini,
                                beginAtZero: horizontal,
                                ticks: { color: textColor, font: { size: 10, weight: '600' }, callback(value) { return shortLabel(this.getLabelForValue(value), false); } },
                                grid: { color: gridColor, drawBorder: false },
                            },
                            y: {
                                display: !mini,
                                beginAtZero: !horizontal,
                                ticks: { color: textColor, font: { size: 10, weight: '600' }, callback(value) { return horizontal ? shortLabel(this.getLabelForValue(value), false) : value; } },
                                grid: { color: gridColor, drawBorder: false },
                            },
                        };

                        instances[canvas.id] = new window.Chart(ctx, {
                            type: requestedType,
                            data: {
                                labels: labels.map((label) => shortLabel(label, mini)),
                                datasets: [dataset],
                            },
                            options: {
                                indexAxis: horizontal ? 'y' : 'x',
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: {
                                    duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : (mini ? 550 : 900),
                                    easing: 'easeOutQuart',
                                },
                                layout: { padding: mini ? 0 : 4 },
                                plugins: {
                                    legend: {
                                        display: !mini && ['doughnut', 'polarArea'].includes(requestedType),
                                        position: 'bottom',
                                        labels: {
                                            color: 'rgba(255,255,255,.58)',
                                            usePointStyle: true,
                                            boxWidth: 7,
                                            padding: 12,
                                            font: { size: 10, weight: '700' },
                                        },
                                    },
                                    tooltip: {
                                        enabled: !mini,
                                        backgroundColor: '#0f0f0f',
                                        borderColor: 'rgba(212,175,55,.35)',
                                        borderWidth: 1,
                                        titleColor: '#d4af37',
                                        bodyColor: '#ffffff',
                                        padding: 10,
                                        callbacks: {
                                            title(items) {
                                                const index = items[0]?.dataIndex ?? 0;
                                                return String(labels[index] ?? '').replace(/\n/g, ' · ');
                                            },
                                            label(item) {
                                                return ` ${numberFormatter.format(item.raw ?? 0)}`;
                                            },
                                        },
                                    },
                                },
                                cutout: requestedType === 'doughnut' ? (mini ? '72%' : '64%') : undefined,
                                scales: ['doughnut', 'polarArea'].includes(requestedType)
                                    ? {}
                                    : requestedType === 'radar'
                                        ? {
                                            r: {
                                                beginAtZero: true,
                                                angleLines: { color: gridColor },
                                                grid: { color: gridColor },
                                                pointLabels: { color: textColor, font: { size: 10, weight: '700' } },
                                                ticks: { display: false },
                                            },
                                        }
                                        : axes,
                            },
                        });
                    });
                };

                const resizeCharts = () => {
                    Object.values(instances).forEach((chart) => chart?.resize());
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', bootCharts);
                } else {
                    bootCharts();
                }

                window.addEventListener('ub:analytics:resize', () => setTimeout(resizeCharts, 140));
                window.addEventListener('resize', () => setTimeout(resizeCharts, 80));
            })();
        </script>
    @endif
</x-app-layout>
