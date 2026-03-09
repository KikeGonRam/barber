<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Portafolio Digital</span></h2>
                <p class="ui-subtitle">Gestiona la vitrina de tus mejores trabajos para atraer clientes.</p>
            </div>
            <a href="{{ route('barber.portfolio.create') }}" class="ui-btn">
                <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Publicar Trabajo
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($works as $work)
                    <article class="ui-card-premium p-0 overflow-hidden group">
                        <div class="aspect-square relative">
                            @if($work->images->first())
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($work->images->first()->image) }}" class="h-full w-full object-cover group-hover:scale-110 transition-transform duration-700">
                            @else
                                <div class="h-full w-full bg-white/5 flex items-center justify-center">
                                    <svg class="h-12 w-12 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" stroke-width="1"/></svg>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-60"></div>
                            
                            <!-- Quick Delete -->
                            <form action="{{ route('barber.portfolio.destroy', $work) }}" method="POST" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-2 rounded-lg bg-red-500/80 text-white hover:bg-red-600 transition-all shadow-lg" onsubmit="return confirm('¿Eliminar este trabajo?');">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                        </div>
                        <div class="p-5">
                            <h3 class="text-sm font-black text-white uppercase truncate">{{ $work->title }}</h3>
                            <div class="mt-4 flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-muted">
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-3 w-3 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                    {{ $work->reactions->count() }}
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <svg class="h-3 w-3 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                    {{ $work->comments->count() }}
                                </span>
                                <span>{{ $work->created_at->format('d/m/Y') }}</span>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full py-20 text-center ui-card-premium border-dashed border-white/10 bg-transparent">
                        <p class="text-muted italic">Aún no has publicado trabajos en tu portafolio.</p>
                        <a href="{{ route('barber.portfolio.create') }}" class="mt-4 inline-block text-gold font-black uppercase text-xs hover:underline">Comenzar ahora &rarr;</a>
                    </div>
                @endforelse
            </div>

            <div class="mt-8">
                {{ $works->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
