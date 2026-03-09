<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Muro de <span class="text-gold">Inspiración</span></h2>
                <p class="ui-subtitle">Descubre las últimas tendencias y trabajos de nuestros maestros.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 space-y-12">
            @forelse($works as $work)
                <article class="ui-card-premium p-0 overflow-hidden group" x-data="{ 
                    liked: {{ $work->isReactedBy(auth()->user()) ? 'true' : 'false' }},
                    saved: {{ $work->isSavedBy(auth()->user()) ? 'true' : 'false' }},
                    likesCount: {{ $work->reactions->count() }},
                    showComments: false,
                    async toggleLike() {
                        const res = await fetch('{{ route('social.react', $work) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
                        });
                        const data = await res.json();
                        this.liked = data.status === 'added';
                        this.likesCount = data.count;
                    },
                    async toggleSave() {
                        const res = await fetch('{{ route('social.save', $work) }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
                        });
                        const data = await res.json();
                        this.saved = data.status === 'added';
                    }
                }">
                    <!-- Header -->
                    <div class="p-4 flex items-center justify-between border-b border-white/5 bg-white/5">
                        <a href="{{ route('barbers.public.show', $work->barberUser->barberProfile) }}" class="flex items-center gap-3 group/author">
                            <div class="h-10 w-10 rounded-full border-2 border-gold/30 p-0.5 overflow-hidden">
                                @if($work->barberUser->barberProfile->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($work->barberUser->barberProfile->foto) }}" class="h-full w-full object-cover rounded-full">
                                @else
                                    <div class="h-full w-full bg-bg-accent flex items-center justify-center rounded-full text-xs font-black text-gold">
                                        {{ substr($work->barberUser->name, 0, 2) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm font-black text-white uppercase tracking-tight group-hover/author:text-gold transition-colors">{{ $work->barberUser->name }}</p>
                                <p class="text-[9px] font-bold text-muted uppercase tracking-widest">Maestro Barbero</p>
                            </div>
                        </a>
                        <button class="text-muted hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" stroke-width="2"/></svg>
                        </button>
                    </div>

                    <!-- Image Content -->
                    <div class="aspect-square bg-black relative">
                        @if($work->images->count() > 1)
                            <!-- Simplified Carousel Placeholder -->
                            <div class="absolute top-4 right-4 z-10 px-2 py-1 rounded-full bg-black/60 backdrop-blur-md border border-white/10 text-[9px] font-black text-white">
                                1/{{ $work->images->count() }}
                            </div>
                        @endif
                        <img src="{{ \Illuminate\Support\Facades\Storage::url($work->images->first()?->image) }}" class="h-full w-full object-cover">
                    </div>

                    <!-- Actions -->
                    <div class="p-4 space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-5">
                                <button @click="toggleLike()" class="transition-transform active:scale-125" :class="liked ? 'text-red-500' : 'text-white hover:text-red-400'">
                                    <svg class="h-7 w-7" :fill="liked ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                </button>
                                <button @click="showComments = !showComments" class="text-white hover:text-gold transition-colors">
                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                </button>
                                <button class="text-white hover:text-gold transition-colors">
                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg>
                                </button>
                            </div>
                            <button @click="toggleSave()" :class="saved ? 'text-gold' : 'text-white hover:text-gold'" class="transition-colors">
                                <svg class="h-7 w-7" :fill="saved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
                            </button>
                        </div>

                        <!-- Likes & Caption -->
                        <div class="space-y-1.5">
                            <p class="text-sm font-black text-white"><span x-text="likesCount"></span> me gusta</p>
                            <p class="text-sm text-white font-medium leading-relaxed">
                                <span class="font-black mr-2">{{ $work->barberUser->name }}</span>
                                {{ $work->description ?: $work->title }}
                            </p>
                            <p class="text-[9px] font-bold text-muted uppercase tracking-widest mt-2">{{ $work->created_at->diffForHumans() }}</p>
                        </div>

                        <!-- Comments Section (Interactive) -->
                        <div x-show="showComments" x-transition class="pt-4 border-t border-white/5 space-y-4">
                            @foreach($work->comments as $comment)
                                <div class="flex items-start gap-3">
                                    <p class="text-xs text-white leading-relaxed">
                                        <span class="font-black mr-2">{{ $comment->user->name }}</span>
                                        {{ $comment->comment }}
                                    </p>
                                </div>
                            @endforeach
                            
                            <form method="POST" action="{{ route('social.comment', $work) }}" class="flex gap-3 pt-2">
                                @csrf
                                <input type="text" name="comment" placeholder="Añade un comentario..." class="flex-1 bg-transparent border-0 border-b border-white/10 text-xs focus:ring-0 focus:border-gold transition-colors text-white py-2">
                                <button type="submit" class="text-xs font-black text-gold uppercase tracking-widest">Publicar</button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="py-20 text-center text-muted">Aún no hay publicaciones inspiradoras.</div>
            @endforelse

            <div class="mt-8">
                {{ $works->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
