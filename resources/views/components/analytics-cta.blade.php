{{--
    Banner compacto que invita a abrir la página completa de Analítica.
    Reemplaza al grid grande de tarjetas que antes se volcaba dentro del
    dashboard (lo saturaba). El dashboard queda para OPERAR el día a día;
    el análisis profundo vive en su propia página, y este banner es el puente.

    Props:
      titulo      — encabezado del banner
      descripcion — una frase explicando qué encontrará el usuario
      cta         — texto del botón (por defecto "Ver analítica")
--}}
@props([
    'titulo' => 'Analítica avanzada',
    'descripcion' => 'Descubre patrones y predicciones calculadas automáticamente sobre tu historial.',
    'cta' => 'Ver analítica',
])

<a href="{{ route('analytics.index') }}"
   class="group flex flex-col sm:flex-row sm:items-center gap-4 rounded-2xl border border-gold/15 bg-gradient-to-r from-gold/[0.06] to-transparent p-5 hover:border-gold/35 transition-all">
    <div class="h-12 w-12 rounded-xl bg-gold/10 flex items-center justify-center text-gold shrink-0">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.7 8.3l-4.2 4.2-2.8-2.8L7 14.4"/>
        </svg>
    </div>
    <div class="flex-1">
        <p class="text-[9px] font-black uppercase tracking-widest text-gold/70">UrbanBlade Analytics</p>
        <h3 class="text-sm font-black text-white uppercase mt-0.5">{{ $titulo }}</h3>
        <p class="text-[11px] text-muted mt-1 leading-snug max-w-xl">{{ $descripcion }}</p>
    </div>
    <span class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-gold text-black text-[10px] font-black uppercase tracking-widest shrink-0 group-hover:gap-2.5 transition-all">
        {{ $cta }}
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </span>
</a>
