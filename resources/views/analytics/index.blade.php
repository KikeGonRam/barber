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
        $visibles = collect($secciones ?? [])->keys()->filter(fn ($seccion) => ($porSeccion[$seccion] ?? collect())->isNotEmpty());
        $kpiStyles = [
            'gold' => ['borde' => 'border-gold/20', 'fondo' => 'bg-gold/[0.06]', 'texto' => 'text-gold'],
            'info' => ['borde' => 'border-sky-400/20', 'fondo' => 'bg-sky-500/[0.06]', 'texto' => 'text-sky-300'],
            'success' => ['borde' => 'border-emerald-400/20', 'fondo' => 'bg-emerald-500/[0.06]', 'texto' => 'text-emerald-300'],
            'warning' => ['borde' => 'border-amber-400/20', 'fondo' => 'bg-amber-500/[0.06]', 'texto' => 'text-amber-300'],
            'danger' => ['borde' => 'border-rose-400/20', 'fondo' => 'bg-rose-500/[0.06]', 'texto' => 'text-rose-300'],
        ];
    @endphp

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8" x-data="{ tab: '{{ $visibles->first() ?? 'resumen' }}', selectTab(value) { this.tab = value; setTimeout(() => window.dispatchEvent(new Event('resize')), 80); } }">
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

            <nav class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-2 mb-6" aria-label="Secciones de analítica">
                @foreach($visibles as $seccion)
                    @php $meta = $secciones[$seccion]; @endphp
                    <button @click="selectTab('{{ $seccion }}')" :class="tab === '{{ $seccion }}' ? '{{ $meta['activo'] }}' : 'border-white/[0.07] bg-white/[0.025] text-white/55 hover:bg-white/[0.05]'" class="text-left rounded-2xl border p-4 transition-all">
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
                @foreach([['operacion','Operación'],['clientes','Clientes y ventas'],['prediccion','Alertas'],['resumen','Resumen']] as $accion)
                    @if($visibles->contains($accion[0]))
                        <button @click="selectTab('{{ $accion[0] }}')" class="rounded-xl border border-white/[0.08] bg-white/[0.03] px-3 py-2 text-[9px] font-black uppercase tracking-widest text-white/55 hover:border-gold/35 hover:text-gold transition-all">{{ $accion[1] }}</button>
                    @endif
                @endforeach
            </div>

            @foreach($visibles as $seccion)
                @php
                    $meta = $secciones[$seccion];
                    $seccionInsights = $porSeccion[$seccion] ?? collect();
                @endphp
                <section x-show="tab === '{{ $seccion }}'" x-cloak class="space-y-5">
                    <div class="rounded-2xl border border-white/[0.07] bg-[#111] p-5 flex gap-4 items-start">
                        <span class="h-2.5 w-2.5 rounded-full bg-gold mt-2 shrink-0 shadow-[0_0_14px_rgba(212,175,55,.45)]"></span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest {{ $meta['acento'] }} mb-1">{{ $meta['titulo'] }}</p>
                            <p class="text-sm text-white/65 leading-relaxed">{{ $meta['intro'] }}</p>
                        </div>
                    </div>

                    <x-analytics-insights :insights="$seccionInsights" titulo="Hallazgos relevantes" :showCharts="true" idPrefix="section-chart-{{ $seccion }}" />
                    @if($seccion === 'resumen' && $diagnosticoInsights->isNotEmpty())
                        <details class="group rounded-2xl border border-white/[0.08] bg-[#111] p-4">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-[10px] font-black uppercase tracking-widest text-white/60">
                                <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Diagnóstico de datos</span>
                                <span class="text-white/35 transition-transform group-open:rotate-180">⌄</span>
                            </summary>
                            <div class="mt-5"><x-analytics-insights :insights="$diagnosticoInsights" titulo="Calidad y limpieza" :showCharts="true" idPrefix="diagnostic-chart" /></div>
                        </details>
                    @endif
                </section>
            @endforeach
        @endif
    </div>

</x-app-layout>
