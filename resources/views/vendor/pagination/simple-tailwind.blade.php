@if ($paginator->hasPages())
<nav class="flex items-center justify-between gap-4 mt-6" role="navigation" aria-label="Paginación">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
        <span aria-disabled="true"
              class="inline-flex items-center gap-1.5 rounded-xl border border-white/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white/15 cursor-not-allowed select-none">
            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Anterior
        </span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
           class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-white hover:border-gold/40 hover:bg-gold/5 active:scale-95 transition-all duration-150">
            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            Anterior
        </a>
    @endif

    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-muted">
        Página <span class="text-gold">{{ $paginator->currentPage() }}</span>
    </span>

    {{-- Next --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next"
           class="inline-flex items-center gap-1.5 rounded-xl border border-white/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-white hover:border-gold/40 hover:bg-gold/5 active:scale-95 transition-all duration-150">
            Siguiente
            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        </a>
    @else
        <span aria-disabled="true"
              class="inline-flex items-center gap-1.5 rounded-xl border border-white/5 px-3 py-1.5 text-[10px] font-black uppercase tracking-widest text-white/15 cursor-not-allowed select-none">
            Siguiente
            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
        </span>
    @endif

</nav>
@endif
