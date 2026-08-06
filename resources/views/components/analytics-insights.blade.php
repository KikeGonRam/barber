@props(['insights' => [], 'titulo' => 'Analítica avanzada', 'showCharts' => false, 'idPrefix' => 'insight-chart'])

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
    $wideTypes = [
        'resumen_ejecutivo',
        'demanda_horas_pico',
        'demanda_horas_pico_propia',
        'clasificacion_cancelacion',
        'alertas_cancelacion',
        'matriz_resultados_cancelacion',
        'regresion_facturacion',
    ];
    $visualLabels = \App\Models\AnalyticsInsight::VISUAL_LABELS;
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

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            @foreach($items as $insight)
                @php
                    $color = data_get($insight, 'color', 'gold');
                    $c = $paleta[$color] ?? $paleta['gold'];
                    $tit = data_get($insight, 'titulo');
                    $dato = data_get($insight, 'valor_destacado');
                    $msg = data_get($insight, 'mensaje');
                    $grafica = data_get($insight, 'grafica');
                    $tipoInsight = data_get($insight, 'tipo', 'insight');
                    $tipoVisual = \App\Models\AnalyticsInsight::visualTypeFor($tipoInsight, $grafica);
                    $chartId = \Illuminate\Support\Str::slug($idPrefix.'-'.$tipoInsight.'-'.$loop->index);
                    $labels = collect(data_get($grafica, 'labels', []));
                    $values = collect(data_get($grafica, 'valores', []));
                    // Algunos insights de Spark traen una fila de encabezado
                    // ("LEYENDA") mezclada como si fuera una categoría real —
                    // mostrarla como un dato más confunde a un usuario no
                    // técnico, así que se descarta antes de graficar.
                    $placeholderIndexes = $labels->filter(fn ($label) => preg_match('/^\s*leyenda\s*$/i', (string) $label))->keys();
                    if ($placeholderIndexes->isNotEmpty() && $placeholderIndexes->count() < $labels->count()) {
                        $labels = $labels->reject(fn ($label, $index) => $placeholderIndexes->contains($index))->values();
                        $values = $values->reject(fn ($value, $index) => $placeholderIndexes->contains($index))->values();
                    }
                    $heatmapIsFallback = false;
                    if ($showCharts && $tipoVisual === 'heatmap' && $values->isEmpty() && filled($dato)) {
                        $heatmapIsFallback = true;
                        $labels = collect([(string) $dato]);
                        $values = collect([100]);
                        $grafica = ['tipo' => 'heatmap', 'labels' => $labels->all(), 'valores' => $values->all()];
                    }
                    $hasVisual = $showCharts && $values->isNotEmpty() && ($grafica || $tipoVisual === 'heatmap');
                    $isMatrix = $hasVisual && $tipoVisual === 'matrix';
                    $isHeatmap = $hasVisual && $tipoVisual === 'heatmap';
                    $isFactorList = $hasVisual && $tipoVisual === 'factor-list';
                    $isWide = $hasVisual && in_array($tipoInsight, $wideTypes, true);
                    // Solo se recorta si el mensaje realmente es más largo que el
                    // límite — así evitamos mostrar el mismo texto completo dos
                    // veces (una vez "resumido" y otra dentro de "Ver hallazgo")
                    // cuando el mensaje ya es corto, como pasa con los insights
                    // de pocas palabras del rol cliente.
                    $briefLimit = $hasVisual ? 22 : 34;
                    $totalPalabras = str_word_count(strip_tags($msg ?? ''));
                    $estaTruncado = $totalPalabras > $briefLimit;
                    $brief = $estaTruncado ? \Illuminate\Support\Str::words($msg ?? '', $briefLimit) : ($msg ?? '');
                    $maxValue = max((float) $values->max(), 1);
                    $visualLabel = $visualLabels[$tipoVisual] ?? 'Indicador';
                    $progressValue = null;
                    if (preg_match('/([\d]+(?:[.,]\d+)?)\s*%/', (string) $dato, $matches)) {
                        $progressValue = max(3, min(100, (float) str_replace(',', '.', $matches[1])));
                    }
                    $chartConfig = [
                        'graph' => $grafica,
                        'type' => $tipoVisual,
                        'tone' => $color,
                        'accent' => $c['hex'],
                        'insightType' => $tipoInsight,
                    ];
                @endphp

                <article class="{{ $isWide ? 'xl:col-span-2' : '' }} ub-analytics-card group overflow-hidden rounded-[8px] border {{ $c['border'] }} {{ $c['bg'] }} p-4 transition duration-300 hover:-translate-y-0.5 hover:border-white/15 sm:p-5">
                    <div class="{{ $isWide ? 'grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_320px]' : 'space-y-4' }}">
                        <div class="min-w-0">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="mb-2 flex flex-wrap items-center gap-2">
                                        <span class="rounded-full border border-white/[0.08] bg-white/[0.04] px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.16em] text-white/42">{{ $visualLabel }}</span>
                                        @if($hasVisual)
                                            <span class="rounded-full border {{ $c['border'] }} px-2 py-0.5 text-[8px] font-black uppercase tracking-[0.16em] {{ $c['texto'] }}">Con gráfica</span>
                                        @endif
                                    </div>
                                    <p class="text-[10px] font-black uppercase tracking-[0.18em] {{ $c['texto'] }}">{{ $tit }}</p>
                                    <p class="mt-1 text-2xl font-black leading-tight text-white sm:text-3xl">{{ $dato }}</p>
                                </div>
                                <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-[8px] border border-white/[0.08] bg-white/[0.035]">
                                    <span class="h-2 w-2 rounded-full {{ $c['dot'] }}"></span>
                                </span>
                            </div>

                            @if($isMatrix)
                                <div class="rounded-[8px] border border-white/[0.07] bg-black/20 p-4" aria-label="Matriz de resultados">
                                    <div class="mb-3 flex items-center justify-between gap-3">
                                        <p class="text-[9px] font-black uppercase tracking-[0.18em] text-white/38">Resultado del modelo</p>
                                        <span class="rounded-full border border-white/[0.08] bg-white/[0.035] px-2 py-0.5 text-[9px] font-black text-white/45">
                                            {{ number_format((float) $values->sum(), 0) }} casos
                                        </span>
                                    </div>

                                    <div class="grid grid-cols-[74px_1fr_1fr] gap-2 text-center">
                                        <div></div>
                                        <div class="rounded-[8px] border border-emerald-400/12 bg-emerald-500/[0.045] px-2 py-2 text-[9px] font-black uppercase tracking-wider text-emerald-300">Acierto</div>
                                        <div class="rounded-[8px] border border-rose-400/12 bg-rose-500/[0.045] px-2 py-2 text-[9px] font-black uppercase tracking-wider text-rose-300">Revisar</div>

                                        @foreach($labels as $label)
                                            @php
                                                $value = (float) ($values[$loop->index] ?? 0);
                                                $lower = mb_strtolower((string) $label);
                                                $isGood = str_contains($lower, 'verdadero')
                                                    || str_contains($lower, 'acierto')
                                                    || str_contains($lower, 'correct')
                                                    || in_array($loop->index, [0, 3], true);
                                                $cellClass = $isGood
                                                    ? 'border-emerald-400/20 bg-emerald-500/[0.08] text-emerald-200'
                                                    : 'border-amber-400/20 bg-amber-500/[0.08] text-amber-200';
                                            @endphp

                                            @if($loop->index === 0)
                                                <div class="row-span-2 grid place-items-center rounded-[8px] border border-white/[0.06] bg-white/[0.025] px-2 text-[9px] font-black uppercase tracking-wider text-white/42 [writing-mode:vertical-rl] rotate-180">Real</div>
                                            @endif

                                            <div class="rounded-[8px] border {{ $cellClass }} p-3 text-left">
                                                <p class="text-2xl font-black text-white">{{ number_format($value, 0) }}</p>
                                                <p class="mt-1 whitespace-pre-line text-[9px] font-bold uppercase tracking-wider opacity-70">{{ $label }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif($isHeatmap)
                                @php
                                    $heatmapPoints = $labels->map(function ($label, $index) use ($values, $maxValue, $heatmapIsFallback) {
                                        $value = (float) ($values[$index] ?? 0);
                                        $intensity = max(18, min(100, (int) round(($value / max($maxValue, 1)) * 100)));

                                        return [
                                            'label' => (string) $label,
                                            'value' => $value,
                                            'display' => $heatmapIsFallback ? 'Pico' : number_format($value, 0),
                                            'caption' => $heatmapIsFallback ? 'punto caliente' : 'citas',
                                            'intensity' => $intensity,
                                            'alpha' => number_format($intensity / 100, 2, '.', ''),
                                        ];
                                    });
                                    $heatmapTotal = (float) $heatmapPoints->sum('value');
                                    $heatmapAverage = (float) ($heatmapPoints->avg('value') ?? 0);
                                    $peak = $heatmapPoints->sortByDesc('value')->first();
                                    $topHeatmap = $heatmapPoints->sortByDesc('value')->take(3)->values();
                                    $heatmapColumns = min(max($heatmapPoints->count(), 1), 14);
                                @endphp

                                <div class="ub-heatmap-board rounded-[8px] border border-amber-300/15 bg-black/20 p-4" aria-label="Mapa de calor de demanda">
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <div class="rounded-[8px] border border-white/[0.07] bg-white/[0.035] p-3">
                                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-amber-300">Mayor demanda</p>
                                            <p class="mt-1 text-xl font-black text-white">{{ $peak['label'] ?? $dato }}</p>
                                            <p class="text-[10px] font-bold text-white/42">
                                                {{ $heatmapIsFallback ? 'Horario detectado por Spark' : number_format((float) ($peak['value'] ?? 0), 0).' citas detectadas' }}
                                            </p>
                                        </div>
                                        <div class="rounded-[8px] border border-white/[0.07] bg-white/[0.035] p-3">
                                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-white/38">Promedio</p>
                                            <p class="mt-1 text-xl font-black text-white">{{ $heatmapIsFallback ? 'N/D' : number_format($heatmapAverage, 1) }}</p>
                                            <p class="text-[10px] font-bold text-white/42">{{ $heatmapIsFallback ? 'serie no disponible' : 'citas por bloque' }}</p>
                                        </div>
                                        <div class="rounded-[8px] border border-white/[0.07] bg-white/[0.035] p-3">
                                            <p class="text-[9px] font-black uppercase tracking-[0.16em] text-white/38">Volumen leído</p>
                                            <p class="mt-1 text-xl font-black text-white">{{ $heatmapIsFallback ? 'Punto clave' : number_format($heatmapTotal, 0) }}</p>
                                            <p class="text-[10px] font-bold text-white/42">{{ $heatmapIsFallback ? 'sin inventar volumen' : $heatmapPoints->count().' bloques analizados' }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 overflow-x-auto pb-1">
                                        <div class="ub-heatmap-grid min-w-[520px]" style="grid-template-columns: repeat({{ $heatmapColumns }}, minmax(58px, 1fr));">
                                            @foreach($heatmapPoints as $point)
                                                @php $isPeak = abs($point['value'] - (float) ($peak['value'] ?? -1)) < 0.0001; @endphp
                                                <div class="ub-heatmap-cell {{ $isPeak ? 'is-peak' : '' }}" style="--heat: {{ $point['alpha'] }}; --heat-percent: {{ $point['intensity'] }}%;">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="truncate text-[10px] font-black text-white">{{ $point['label'] }}</span>
                                                        @if($isPeak)
                                                            <span class="rounded-full bg-black/45 px-1.5 py-0.5 text-[8px] font-black uppercase tracking-wider text-amber-200">Pico</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-3 text-2xl font-black text-white">{{ $point['display'] }}</p>
                                                    <p class="mt-0.5 text-[9px] font-bold uppercase tracking-wider text-white/52">{{ $point['caption'] }}</p>
                                                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-black/35">
                                                        <span class="block h-full rounded-full bg-white/85" style="width: {{ $point['intensity'] }}%"></span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                        <div>
                                            <div class="h-2 rounded-full bg-[linear-gradient(90deg,rgba(245,158,11,.18),rgba(245,158,11,.52),rgba(245,158,11,.95))]"></div>
                                            <div class="mt-1 flex justify-between text-[9px] font-black uppercase tracking-wider text-white/35">
                                                <span>Baja</span>
                                                <span>Media</span>
                                                <span>Alta</span>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap gap-2">
                                            @foreach($topHeatmap as $point)
                                                <span class="rounded-full border border-amber-300/20 bg-amber-500/[0.08] px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-amber-200">
                                                    {{ $loop->iteration }} · {{ $point['label'] }} · {{ $heatmapIsFallback ? 'pico' : number_format($point['value'], 0) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @elseif($isFactorList)
                                <div class="space-y-3 rounded-[8px] border border-white/[0.06] bg-black/15 p-4" aria-label="Importancia de factores">
                                    @foreach($labels as $label)
                                        @php
                                            $value = (float) ($values[$loop->index] ?? 0);
                                            $width = max(3, min(100, $value));
                                        @endphp
                                        <div>
                                            <div class="mb-1 flex items-center justify-between gap-3">
                                                <span class="truncate text-[11px] font-bold text-white/62">{{ $label }}</span>
                                                <span class="text-[11px] font-black text-amber-300">{{ number_format($value, 1) }}%</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-white/[0.07]">
                                                <span class="block h-full rounded-full bg-gradient-to-r from-amber-400 to-rose-400" style="width: {{ $width }}%"></span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @elseif($hasVisual)
                                <div class="{{ $isWide ? 'h-[300px]' : 'h-[240px]' }} ub-chart-frame p-3">
                                    <canvas
                                        id="{{ $chartId }}"
                                        data-ub-analytics-chart
                                        data-chart-config="{{ $chartId }}-config"
                                        aria-label="{{ $tit }}"
                                    ></canvas>
                                    <script id="{{ $chartId }}-config" type="application/json">
                                        {!! json_encode($chartConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                                    </script>
                                </div>
                            @else
                                <div class="rounded-[8px] border border-white/[0.06] bg-black/15 p-4">
                                    @if($progressValue !== null)
                                        <div class="mb-4 h-2 overflow-hidden rounded-full bg-white/[0.07]">
                                            <span class="block h-full rounded-full {{ $c['dot'] }}" style="width: {{ $progressValue }}%"></span>
                                        </div>
                                    @else
                                        <div class="mb-4 flex gap-1">
                                            <span class="h-1.5 flex-1 rounded-full {{ $c['dot'] }}"></span>
                                            <span class="h-1.5 flex-1 rounded-full {{ $c['dot'] }} opacity-70"></span>
                                            <span class="h-1.5 flex-1 rounded-full {{ $c['dot'] }} opacity-40"></span>
                                        </div>
                                    @endif
                                    <p class="max-w-2xl text-sm leading-relaxed text-white/58">{{ $brief }}</p>
                                </div>
                            @endif
                        </div>

                        <div class="{{ $isWide ? 'lg:border-l lg:border-white/[0.07] lg:pl-5' : '' }} flex flex-col justify-between gap-3">
                            @if($hasVisual)
                                <p class="text-sm leading-relaxed text-white/58">{{ $brief }}</p>
                            @endif

                            @if($estaTruncado)
                                <details class="group rounded-[8px] border border-white/[0.07] bg-white/[0.025] px-3 py-2">
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-[10px] font-black uppercase tracking-[0.16em] text-white/45 transition-colors hover:text-gold">
                                        <span>Ver hallazgo</span>
                                        <svg class="h-3.5 w-3.5 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </summary>
                                    <p class="mt-3 text-[12px] leading-relaxed text-white/62">{{ $msg }}</p>
                                </details>
                            @elseif(!$hasVisual)
                                {{-- Mensaje corto sin gráfica: ya se muestra completo arriba, no hay nada que expandir. --}}
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
