<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Portafolio</span></h2>
                <p class="ui-subtitle">Tu vitrina profesional — los trabajos que hablan por ti.</p>
            </div>
            <a href="{{ route('barber.portfolio.create') }}" class="ui-btn">
                <svg class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Publicar Trabajo
            </a>
        </div>
    </x-slot>

    <div class="space-y-6 py-4">
        <x-auth-session-status :status="session('status')" />

        {{-- ── STATS ──────────────────────────────────────── --}}
        <section class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-2xl border border-white/8 bg-[#111] p-5 text-center">
                <p class="text-3xl font-black text-white">{{ $stats['total_works'] }}</p>
                <p class="text-[9px] font-black uppercase tracking-widest text-muted mt-1">Trabajos</p>
            </div>
            <div class="rounded-2xl border border-red-500/20 bg-red-500/5 p-5 text-center">
                <p class="text-3xl font-black text-red-400">{{ $stats['total_reactions'] }}</p>
                <p class="text-[9px] font-black uppercase tracking-widest text-red-400/60 mt-1">Reacciones</p>
            </div>
            <div class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-5 text-center">
                <p class="text-3xl font-black text-blue-400">{{ $stats['total_comments'] }}</p>
                <p class="text-[9px] font-black uppercase tracking-widest text-blue-400/60 mt-1">Comentarios</p>
            </div>
            <div class="rounded-2xl border border-gold/20 bg-gold/5 p-5 text-center">
                <p class="text-3xl font-black text-gold">{{ $stats['total_saves'] }}</p>
                <p class="text-[9px] font-black uppercase tracking-widest text-gold/60 mt-1">Guardados</p>
            </div>
        </section>

        {{-- ── GALERÍA ─────────────────────────────────────── --}}
        @if($works->isEmpty())
            <section class="rounded-2xl border border-dashed border-white/10 py-24 text-center">
                <svg class="h-14 w-14 text-white/5 mx-auto mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <p class="text-sm font-bold text-muted uppercase tracking-widest">Aún no has publicado trabajos</p>
                <p class="text-[11px] text-muted/50 mt-1 mb-5">Tu portafolio es tu carta de presentación</p>
                <a href="{{ route('barber.portfolio.create') }}" class="ui-btn">
                    Publicar mi primer trabajo &rarr;
                </a>
            </section>
        @else
            <section class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach($works as $work)
                    <article class="group rounded-2xl border border-white/8 bg-[#111] overflow-hidden hover:border-white/15 transition-all duration-300">
                        {{-- Image --}}
                        @php
                            $firstMedia  = $work->images->first();
                            $isFirstVideo = $firstMedia && ($firstMedia->type ?? 'image') === 'video';
                            $hasVideo    = $work->images->contains(fn($i) => ($i->type ?? 'image') === 'video');
                            $mediaCount  = $work->images->count();
                        @endphp
                        <div class="aspect-square relative overflow-hidden bg-[#0d0d0d]">
                            @if($firstMedia)
                                @if($isFirstVideo)
                                    <video src="{{ \Illuminate\Support\Facades\Storage::url($firstMedia->image) }}"
                                           class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-700"
                                           muted preload="metadata" playsinline></video>
                                    <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/10 transition-colors">
                                        <div class="h-10 w-10 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                            <svg class="h-5 w-5 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                    </div>
                                @else
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($firstMedia->image) }}"
                                         class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-700 grayscale-[20%] group-hover:grayscale-0"
                                         loading="lazy" alt="{{ $work->title }}">
                                @endif
                            @else
                                <div class="h-full w-full flex items-center justify-center">
                                    <svg class="h-12 w-12 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif

                            {{-- Gradient overlay --}}
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>

                            {{-- Top badges --}}
                            <div class="absolute top-3 left-3 flex items-center gap-1.5">
                                @if($hasVideo)
                                    <div class="flex items-center gap-1 rounded-lg bg-black/70 backdrop-blur-sm px-1.5 py-1 border border-white/10">
                                        <svg class="h-3 w-3 text-purple-400" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        <span class="text-[8px] font-black text-purple-300">Video</span>
                                    </div>
                                @endif
                                @if($mediaCount > 1)
                                    <div class="flex items-center gap-1 rounded-lg bg-black/70 backdrop-blur-sm px-1.5 py-1 border border-white/10">
                                        <svg class="h-3 w-3 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                        <span class="text-[8px] font-black text-white/60">{{ $mediaCount }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Delete button (desktop hover) --}}
                            <div class="absolute top-3 right-3 hidden sm:block opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                <form action="{{ route('barber.portfolio.destroy', $work) }}" method="POST"
                                      onsubmit="return confirm('¿Eliminar este trabajo del portafolio?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" aria-label="Eliminar trabajo"
                                            class="h-8 w-8 rounded-lg bg-red-500/80 backdrop-blur-sm flex items-center justify-center text-white hover:bg-red-600 transition-all shadow-lg">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>

                        {{-- Info --}}
                        <div class="p-4">
                            <h3 class="text-sm font-black text-white uppercase tracking-tight truncate">{{ $work->title }}</h3>
                            @if($work->description)
                                <p class="text-[10px] text-muted mt-1 line-clamp-2">{{ $work->description }}</p>
                            @endif

                            <div class="mt-4 pt-3 border-t border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3 text-[10px] font-black uppercase tracking-wider">
                                    {{-- Reactions --}}
                                    <span class="flex items-center gap-1 text-red-400">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                        {{ $work->reactions->count() }}
                                    </span>
                                    {{-- Comments --}}
                                    <span class="flex items-center gap-1 text-muted">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                        {{ $work->comments->count() }}
                                    </span>
                                    {{-- Saves --}}
                                    <span class="flex items-center gap-1 text-gold/70">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                        {{ $work->saves->count() }}
                                    </span>
                                </div>
                                <span class="text-[9px] text-muted/50">{{ $work->created_at->format('d/m/Y') }}</span>
                            </div>
                            {{-- Delete button (mobile only) --}}
                            <form action="{{ route('barber.portfolio.destroy', $work) }}" method="POST"
                                  onsubmit="return confirm('¿Eliminar este trabajo?');" class="sm:hidden mt-3">
                                @csrf @method('DELETE')
                                <button type="submit" class="w-full flex items-center justify-center gap-1.5 rounded-xl border border-red-500/20 bg-red-500/5 py-2 text-[10px] font-black uppercase tracking-widest text-red-500 hover:bg-red-500/10 transition-all">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Eliminar trabajo
                                </button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </section>

            <div class="mt-4">{{ $works->links() }}</div>
        @endif
    </div>
</x-app-layout>
