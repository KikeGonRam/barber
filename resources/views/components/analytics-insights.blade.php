{{--
    Tarjetas de "insights" en lenguaje natural (Unidades II-V del proyecto
    Spark), reutilizable en los 4 dashboards por rol.

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
@props(['insights' => [], 'titulo' => 'Analítica avanzada'])

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
@endphp

@if(!empty($insights) && count($insights) > 0)
    <section aria-label="{{ $titulo }}">
        <div class="flex items-center gap-2 mb-3 px-1">
            <svg class="h-4 w-4 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0013 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
            <h3 class="text-[11px] font-black uppercase tracking-widest text-gold">{{ $titulo }}</h3>
            <span class="text-[9px] text-muted">· UrbanBlade Analytics</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($insights as $insight)
                @php
                    $c = $paleta[$insight['color'] ?? $insight->color ?? 'gold'] ?? $paleta['gold'];
                    $tit  = $insight['titulo'] ?? $insight->titulo;
                    $dato = $insight['valor_destacado'] ?? $insight->valor_destacado;
                    $msg  = $insight['mensaje'] ?? $insight->mensaje;
                @endphp
                <article class="rounded-2xl border {{ $c['border'] }} {{ $c['bg'] }} p-4">
                    <p class="text-[9px] font-black uppercase tracking-widest {{ $c['texto'] }}">{{ $tit }}</p>
                    <p class="text-2xl font-black text-white mt-1">{{ $dato }}</p>
                    <p class="text-[11px] text-muted mt-1.5 leading-snug">{{ $msg }}</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
