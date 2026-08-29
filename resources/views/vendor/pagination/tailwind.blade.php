@if ($paginator->hasPages())
<nav class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mt-6" role="navigation" aria-label="Paginación">

    {{-- Info text --}}
    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted">
        @if ($paginator->firstItem())
            Mostrando
            <span class="text-ink">{{ $paginator->firstItem() }}</span>
            –
            <span class="text-ink">{{ $paginator->lastItem() }}</span>
            de
            <span class="text-gold">{{ $paginator->total() }}</span>
            resultados
        @else
            <span class="text-ink">{{ $paginator->count() }}</span> resultados
        @endif
    </p>

    {{-- Page buttons --}}
    <div class="flex items-center gap-1" role="group">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-ink/5 text-ink/15 cursor-not-allowed select-none">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               aria-label="{{ __('pagination.previous') }}"
               class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-ink/10 text-muted hover:text-ink hover:border-gold/40 hover:bg-gold/8 active:scale-95 transition-all duration-150">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
            </a>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)

            {{-- "…" separator --}}
            @if (is_string($element))
                <span aria-hidden="true"
                      class="inline-flex h-8 w-6 items-center justify-center text-[10px] font-black text-ink/45 select-none">
                    ···
                </span>
            @endif

            {{-- Array of page links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page"
                              class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-gold text-black text-[10px] font-black shadow-md shadow-gold/25 select-none">
                            {{ $page }}
                        </span>
                    @else
                        <a href="{{ $url }}"
                           aria-label="{{ __('Go to page :page', ['page' => $page]) }}"
                           class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-ink/10 text-muted hover:text-ink hover:border-gold/40 hover:bg-gold/8 active:scale-95 text-[10px] font-black transition-all duration-150">
                            {{ $page }}
                        </a>
                    @endif
                @endforeach
            @endif

        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               aria-label="{{ __('pagination.next') }}"
               class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-ink/10 text-muted hover:text-ink hover:border-gold/40 hover:bg-gold/8 active:scale-95 transition-all duration-150">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
            </a>
        @else
            <span aria-disabled="true"
                  class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-ink/5 text-ink/15 cursor-not-allowed select-none">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                </svg>
            </span>
        @endif

    </div>
</nav>
@endif
