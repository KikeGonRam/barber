<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Muro de <span class="text-gold">Inspiración</span></h2>
                <p class="ui-subtitle">Descubre los últimos trabajos de nuestros maestros.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-[480px] px-4 space-y-8">

            @forelse($works as $work)
                @php
                    $mediaItems = $work->images->map(fn($img) => [
                        'url'  => \Illuminate\Support\Facades\Storage::url($img->image),
                        'type' => $img->type ?? 'image',
                    ])->values()->toArray();
                    $mediaCount = count($mediaItems);
                    $hasVideo   = collect($mediaItems)->contains('type', 'video');
                    $barberProfile = $work->barberUser?->barberProfile;
                    $barberProfileUrl = $barberProfile?->slug ? route('barbers.public.show', $barberProfile) : null;
                @endphp

                <article class="rounded-2xl border border-white/[0.06] bg-[#111] overflow-hidden"
                         x-data="{
                    liked: {{ $work->isReactedBy(auth()->user()) ? 'true' : 'false' }},
                    saved: {{ $work->isSavedBy(auth()->user()) ? 'true' : 'false' }},
                    likesCount: {{ $work->reactions->count() }},
                    showComments: false,
                    menuOpen: false,
                    slide: 0,
                    media: {{ json_encode($mediaItems) }},

                    async toggleLike() {
                        const r = await fetch('{{ route('social.react', $work) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
                        });
                        const d = await r.json();
                        this.liked = d.status === 'added';
                        this.likesCount = d.count;
                    },

                    async toggleSave() {
                        const r = await fetch('{{ route('social.save', $work) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
                        });
                        const d = await r.json();
                        this.saved = d.status === 'added';
                    },

                    prev() {
                        this.slide = this.slide > 0 ? this.slide - 1 : this.media.length - 1;
                    },
                    next() {
                        this.slide = this.slide < this.media.length - 1 ? this.slide + 1 : 0;
                    }
                }">

                    {{-- ── HEADER ──────────────────────────────── --}}
                    <div class="p-4 flex items-center justify-between border-b border-white/[0.06]">
                        <div @if($barberProfileUrl) onclick="window.location.href='{{ $barberProfileUrl }}'" @endif
                           class="flex items-center gap-3 group/author {{ $barberProfileUrl ? 'cursor-pointer' : '' }}">
                            <div class="h-10 w-10 rounded-full border-2 border-gold/30 p-0.5 overflow-hidden shrink-0">
                                @if($barberProfile?->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($barberProfile->foto) }}"
                                         class="h-full w-full object-cover rounded-full"
                                         alt="Foto de {{ $work->barberUser?->name ?? 'barbero' }}">
                                @else
                                    <div class="h-full w-full bg-gold/10 flex items-center justify-center rounded-full text-xs font-black text-gold">
                                        {{ strtoupper(substr($work->barberUser?->name ?? 'UB', 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-black text-white uppercase tracking-tight group-hover/author:text-gold transition-colors">{{ $work->barberUser?->name ?? 'Barbero' }}</p>
                                <p class="text-[9px] font-bold text-white/50 uppercase tracking-widest">Maestro Barbero · {{ $work->created_at->diffForHumans() }}</p>
                            </div>
                        </div>

                        <div class="relative" @click.outside="menuOpen = false">
                            <button @click="menuOpen = !menuOpen" aria-label="Opciones de la publicación" class="text-white/50 hover:text-white transition p-1.5 rounded-lg hover:bg-white/5">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" stroke-width="2"/>
                                </svg>
                            </button>
                            <div x-show="menuOpen"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 class="absolute right-0 top-9 z-20 min-w-[160px] rounded-xl border border-white/10 bg-[#0d0d0d] shadow-xl py-1"
                                 style="display:none">
                                @if($barberProfileUrl)
                                    <a href="{{ $barberProfileUrl }}"
                                       class="flex items-center gap-2.5 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-white hover:bg-white/5 transition-colors">
                                        <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                        Ver perfil
                                    </a>
                                @endif
                                <button @click="navigator.clipboard.writeText(window.location.href); menuOpen = false"
                                        class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[10px] font-black uppercase tracking-widest text-white/40 hover:text-white hover:bg-white/5 transition-colors">
                                    <svg class="h-3.5 w-3.5 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    Copiar enlace
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── MEDIA CAROUSEL ───────────────────────── --}}
                    <div class="relative aspect-square bg-black select-none overflow-hidden">

                        {{-- Slides --}}
                        <template x-for="(item, i) in media" :key="i">
                            <div x-show="slide === i"
                                 x-transition:enter="transition-opacity duration-200"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="absolute inset-0">

                                {{-- Image --}}
                                <img x-show="item.type === 'image'"
                                     :src="item.url"
                                     class="h-full w-full object-cover"
                                     loading="lazy"
                                     alt="Imagen de la publicación" />

                                {{-- Video --}}
                                <video x-show="item.type === 'video'"
                                       :src="item.url"
                                       class="h-full w-full object-contain bg-black"
                                       controls
                                       playsinline
                                       preload="metadata"
                                       style="display:none">
                                </video>
                            </div>
                        </template>

                        {{-- Prev / Next (only if multiple) --}}
                        @if($mediaCount > 1)
                            <button @click.stop="prev()" aria-label="Imagen anterior"
                                    class="absolute left-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-black/60 backdrop-blur-sm flex items-center justify-center text-white hover:bg-black/80 transition-all z-10">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <button @click.stop="next()" aria-label="Imagen siguiente"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 h-8 w-8 rounded-full bg-black/60 backdrop-blur-sm flex items-center justify-center text-white hover:bg-black/80 transition-all z-10">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif

                        {{-- Top-right badges --}}
                        <div class="absolute top-3 right-3 flex items-center gap-1.5 z-10">
                            @if($hasVideo)
                                <span class="flex items-center gap-1 px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 text-[8px] font-black text-white uppercase">
                                    <svg class="h-2.5 w-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    Video
                                </span>
                            @endif
                            @if($mediaCount > 1)
                                <span class="px-2 py-0.5 rounded-full bg-black/60 backdrop-blur-sm border border-white/10 text-[8px] font-black text-white"
                                      x-text="`${slide + 1} / {{ $mediaCount }}`">
                                    1 / {{ $mediaCount }}
                                </span>
                            @endif
                        </div>

                        {{-- Dot indicators --}}
                        @if($mediaCount > 1)
                            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 flex gap-1.5 z-10">
                                @for($d = 0; $d < $mediaCount; $d++)
                                    <button @click="slide = {{ $d }}"
                                            class="h-1.5 rounded-full transition-all duration-200"
                                            :class="slide === {{ $d }} ? 'w-4 bg-white' : 'w-1.5 bg-white/40'">
                                    </button>
                                @endfor
                            </div>
                        @endif
                    </div>

                    {{-- ── ACTIONS ──────────────────────────────── --}}
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-5">
                                {{-- Like --}}
                                <button @click="toggleLike()" aria-label="Me gusta"
                                        class="transition-transform active:scale-125"
                                        :class="liked ? 'text-red-500' : 'text-white/60 hover:text-red-400'">
                                    <svg class="h-6 w-6" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                </button>
                                {{-- Comments --}}
                                <button @click="showComments = !showComments" aria-label="Ver comentarios"
                                        :class="showComments ? 'text-gold' : 'text-white/60 hover:text-white'"
                                        class="transition-colors">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </button>
                                {{-- Share --}}
                                <button @click="navigator.clipboard.writeText(window.location.href)" aria-label="Compartir publicación"
                                        class="text-white/60 hover:text-white transition-colors">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                    </svg>
                                </button>
                            </div>
                            {{-- Save --}}
                            <button @click="toggleSave()" aria-label="Guardar publicación"
                                    :class="saved ? 'text-gold' : 'text-white/60 hover:text-gold'"
                                    class="transition-colors">
                                <svg class="h-6 w-6" :fill="saved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>
                                </svg>
                            </button>
                        </div>

                        {{-- Likes --}}
                        <p class="text-sm font-black text-white">
                            <span x-text="likesCount"></span> me gusta
                        </p>

                        {{-- Caption --}}
                        <p class="text-sm text-white/80 leading-relaxed">
                            @if($barberProfileUrl)
                                <a href="{{ $barberProfileUrl }}"
                                   class="font-black text-white hover:text-gold transition-colors mr-2">{{ $work->barberUser?->name ?? 'Barbero' }}</a>
                            @else
                                <span class="font-black text-white mr-2">{{ $work->barberUser?->name ?? 'Barbero' }}</span>
                            @endif
                            {{ $work->description ?: $work->title }}
                        </p>

                        {{-- Comments toggle --}}
                        @if($work->comments->count() > 0)
                            <button @click="showComments = !showComments"
                                    class="text-[10px] font-black text-white/50 hover:text-white uppercase tracking-widest transition-colors">
                                <span x-text="showComments ? 'Ocultar comentarios' : 'Ver {{ $work->comments->count() }} comentario{{ $work->comments->count() !== 1 ? 's' : '' }}'">
                                    Ver {{ $work->comments->count() }} comentario{{ $work->comments->count() !== 1 ? 's' : '' }}
                                </span>
                            </button>
                        @endif

                        {{-- Comments section --}}
                        <div x-show="showComments" x-transition class="pt-3 border-t border-white/[0.06] space-y-3">
                            @foreach($work->comments as $comment)
                                <div class="flex items-start gap-2.5">
                                    <div class="h-6 w-6 rounded-full bg-white/10 flex items-center justify-center text-[8px] font-black text-white/60 shrink-0">
                                        {{ strtoupper(substr($comment->user->name, 0, 2)) }}
                                    </div>
                                    <p class="text-xs text-white/70 leading-relaxed">
                                        <span class="font-black text-white mr-1.5">{{ $comment->user->name }}</span>{{ $comment->comment }}
                                    </p>
                                </div>
                            @endforeach

                            <form method="POST" action="{{ route('social.comment', $work) }}" class="flex gap-2 pt-1">
                                @csrf
                                <input type="text" name="comment" placeholder="Añade un comentario..."
                                       class="flex-1 bg-transparent border-0 border-b border-white/10 text-xs text-white placeholder-white/20 focus:ring-0 focus:border-gold/50 transition-colors py-1.5">
                                <button type="submit" class="text-[10px] font-black text-gold uppercase tracking-widest hover:text-white transition-colors">
                                    Publicar
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="py-20 text-center rounded-3xl border border-dashed border-white/[0.06]">
                    <svg class="h-14 w-14 text-white/5 mx-auto mb-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm font-black text-white/45 uppercase tracking-widest">Sin publicaciones aún</p>
                    <p class="text-xs text-white/10 mt-2 max-w-xs mx-auto">Nuestros barberos todavía no han compartido trabajos. Vuelve pronto.</p>
                    <a href="{{ route('home') }}"
                       class="mt-6 inline-flex items-center gap-2 px-8 py-3 rounded-xl border border-gold/30 bg-gold/10 text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">
                        Ver barberos &rarr;
                    </a>
                </div>
            @endforelse

            <div class="mt-4">{{ $works->links() }}</div>
        </div>
    </div>
</x-app-layout>
