@props(['insights' => [], 'titulo' => 'Analitica avanzada', 'showCharts' => false, 'idPrefix' => 'insight-chart'])

@php
    $items = collect($insights);
    $paleta = [
        'gold' => [
            'border' => 'border-gold/20',
            'bg' => 'bg-[linear-gradient(180deg,rgba(212,175,55,.07),rgba(255,255,255,.025))]',
            'texto' => 'text-gold',
            'dot' => 'bg-gold',
            'hex' => '#d4af37',
        ],
        'success' => [
            'border' => 'border-emerald-400/20',
            'bg' => 'bg-[linear-gradient(180deg,rgba(52,211,153,.07),rgba(255,255,255,.025))]',
            'texto' => 'text-emerald-300',
            'dot' => 'bg-emerald-400',
            'hex' => '#34d399',
        ],
        'warning' => [
            'border' => 'border-amber-400/20',
            'bg' => 'bg-[linear-gradient(180deg,rgba(245,158,11,.075),rgba(255,255,255,.025))]',
            'texto' => 'text-amber-300',
            'dot' => 'bg-amber-400',
            'hex' => '#f59e0b',
        ],
        'danger' => [
            'border' => 'border-rose-400/20',
            'bg' => 'bg-[linear-gradient(180deg,rgba(251,113,133,.07),rgba(255,255,255,.025))]',
            'texto' => 'text-rose-300',
            'dot' => 'bg-rose-400',
            'hex' => '#fb7185',
        ],
        'info' => [
            'border' => 'border-sky-400/20',
            'bg' => 'bg-[linear-gradient(180deg,rgba(56,189,248,.07),rgba(255,255,255,.025))]',
            'texto' => 'text-sky-300',
            'dot' => 'bg-sky-400',
            'hex' => '#38bdf8',
        ],
    ];
    $tiposVisuales = [
        'resumen_ejecutivo' => 'line',
        'calidad_datos_etl' => 'doughnut',
        'control_limpieza_datos' => 'doughnut',
        'demanda_horas_pico' => 'line',
        'demanda_horas_pico_propia' => 'line',
        'clientes_en_riesgo' => 'doughnut',
        'segmentacion_clientes' => 'doughnut',
        'perfil_citas_premium' => 'doughnut',
        'fidelizacion_ratio' => 'bar',
        'utilizacion_equipo' => 'bar',
        'utilizacion_propia' => 'bar',
        'engagement_muro_top' => 'bar',
        'engagement_propio' => 'bar',
        'tienda_pedidos' => 'bar',
        'pca_factores' => 'radar',
        'clasificacion_cancelacion' => 'line',
        'alertas_cancelacion' => 'bar',
        'confirmacion_cancelacion_reforzada' => 'bar',
        'matriz_resultados_cancelacion' => 'matrix',
        'regresion_facturacion' => 'line',
    ];
    $wideTypes = [
        'resumen_ejecutivo',
        'demanda_horas_pico',
        'demanda_horas_pico_propia',
        'clasificacion_cancelacion',
        'matriz_resultados_cancelacion',
        'regresion_facturacion',
    ];
@endphp

@if($items->isNotEmpty())
    <section aria-label="{{ $titulo }}">
        <div class="mb-3 flex items-center justify-between gap-3 px-1">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-gold shadow-[0_0_16px_rgba(212,175,55,.45)]"></span>
                <h3 class="text-[11px] font-black uppercase tracking-[0.18em] text-gold">{{ $titulo }}</h3>
            </div>
            <span class="hidden text-[10px] font-bold text-white/35 sm:inline">{{ $items->count() }} resultados</span>
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach($items as $insight)
                @php
                    $color = data_get($insight, 'color', 'gold');
                    $c = $paleta[$color] ?? $paleta['gold'];
                    $tit = data_get($insight, 'titulo');
                    $dato = data_get($insight, 'valor_destacado');
                    $msg = data_get($insight, 'mensaje');
                    $grafica = data_get($insight, 'grafica');
                    $tipoInsight = data_get($insight, 'tipo', 'insight');
                    $tipoVisual = $tiposVisuales[$tipoInsight] ?? data_get($grafica, 'tipo', 'bar');
                    $chartId = \Illuminate\Support\Str::slug($idPrefix.'-'.$tipoInsight.'-'.$loop->index);
                    $labels = collect(data_get($grafica, 'labels', []));
                    $values = collect(data_get($grafica, 'valores', []));
                    $hasVisual = $showCharts && $grafica && $values->isNotEmpty();
                    $isMatrix = $hasVisual && $tipoVisual === 'matrix';
                    $isWide = $hasVisual && in_array($tipoInsight, $wideTypes, true);
                    $brief = \Illuminate\Support\Str::words($msg ?? '', $hasVisual ? 22 : 34);
                @endphp

                <article class="{{ $isWide ? 'xl:col-span-2' : '' }} group overflow-hidden rounded-[8px] border {{ $c['border'] }} {{ $c['bg'] }} p-4 shadow-[0_18px_45px_rgba(0,0,0,.18)] transition duration-300 hover:-translate-y-0.5 hover:border-white/15 sm:p-5">
                    <div class="{{ $isWide ? 'grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_320px]' : 'space-y-4' }}">
                        <div class="min-w-0">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $c['texto'] }}">{{ $tit }}</p>
                                    <p class="mt-1 text-2xl font-black leading-tight text-white sm:text-3xl">{{ $dato }}</p>
                                </div>
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] border border-white/[0.08] bg-white/[0.035]">
                                    <span class="h-2 w-2 rounded-full {{ $c['dot'] }}"></span>
                                </span>
                            </div>

                            @if($isMatrix)
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" aria-label="Matriz de resultados">
                                    @foreach($labels as $label)
                                        @php $value = (float) ($values[$loop->index] ?? 0); @endphp
                                        <div class="rounded-[8px] border border-white/[0.07] bg-black/20 p-4">
                                            <p class="text-2xl font-black text-white">{{ number_format($value, 0) }}</p>
                                            <p class="mt-2 whitespace-pre-line text-[10px] font-bold uppercase tracking-wider text-white/45">{{ $label }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($hasVisual)
                                <div class="{{ $isWide ? 'h-[300px]' : 'h-[240px]' }} rounded-[8px] border border-white/[0.06] bg-black/15 p-3">
                                    <canvas
                                        id="{{ $chartId }}"
                                        data-ub-analytics-chart
                                        data-chart-config="{{ $chartId }}-config"
                                        aria-label="{{ $tit }}"
                                    ></canvas>
                                    <script id="{{ $chartId }}-config" type="application/json">
                                        @json(['graph' => $grafica, 'type' => $tipoVisual, 'tone' => $color, 'accent' => $c['hex'], 'insightType' => $tipoInsight])
                                    </script>
                                </div>
                            @else
                                <p class="max-w-2xl text-sm leading-relaxed text-white/58">{{ $brief }}</p>
                            @endif
                        </div>

                        <div class="{{ $isWide ? 'lg:border-l lg:border-white/[0.07] lg:pl-5' : '' }} flex flex-col justify-between gap-3">
                            @if($hasVisual)
                                <p class="text-sm leading-relaxed text-white/58">{{ $brief }}</p>
                            @endif

                            <details class="group/detail rounded-[8px] border border-white/[0.07] bg-white/[0.025] px-3 py-2">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[10px] font-black uppercase tracking-[0.16em] text-white/45 transition-colors hover:text-gold">
                                    <span>Ver hallazgo</span>
                                    <svg class="h-3.5 w-3.5 transition-transform group-open/detail:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                    </svg>
                                </summary>
                                <p class="mt-3 text-[12px] leading-relaxed text-white/62">{{ $msg }}</p>
                            </details>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
