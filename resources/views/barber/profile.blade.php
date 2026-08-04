<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="ui-title">Mi <span class="text-gold">Perfil Profesional</span></h2>
                <p class="ui-subtitle">Tu presencia digital ante los clientes.</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-500/25 bg-emerald-500/8 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-400">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Perfil activo
                </span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-4">
        <x-auth-session-status :status="session('status')" />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- ── SIDEBAR ─────────────────────────────────── --}}
            <aside class="space-y-5">

                {{-- Card de presentación --}}
                <div class="ui-card-premium overflow-hidden p-0">
                    {{-- Banner degradado --}}
                    <div class="h-28 bg-gradient-to-br from-[#1a1200] via-[#1a1000] to-[#0a0a0a] relative overflow-hidden">
                        <div class="absolute inset-0 opacity-30" style="background:radial-gradient(circle at 60% 50%, rgba(212,175,55,0.4), transparent 60%)"></div>
                        <div class="absolute inset-0" style="background-image:radial-gradient(circle at 2px 2px, rgba(255,255,255,0.03) 1px, transparent 0);background-size:16px 16px;"></div>
                    </div>
                    {{-- Avatar --}}
                    <div class="relative px-6 pb-7 text-center -mt-14">
                        <div class="inline-block relative">
                            <div class="h-28 w-28 rounded-full border-4 border-[#0f0f0f] overflow-hidden mx-auto shadow-2xl">
                                @if($barber->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                                         alt="{{ auth()->user()->name }}"
                                         class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full bg-gradient-to-br from-gold/25 to-gold/5 flex items-center justify-center text-3xl font-black text-gold">
                                        {{ strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div class="absolute bottom-1 right-1 h-5 w-5 rounded-full bg-emerald-500 border-[3px] border-[#0f0f0f]"></div>
                        </div>

                        <h3 class="mt-4 text-xl font-black text-white uppercase tracking-tight">{{ auth()->user()->name }}</h3>
                        <p class="text-[11px] font-black text-gold uppercase tracking-[0.2em] mt-1">{{ $barber->especialidades ? explode(',', $barber->especialidades)[0] : 'Maestro Barbero' }}</p>

                        {{-- Stats reales --}}
                        <div class="mt-6 pt-5 border-t border-white/5 grid grid-cols-3 gap-3">
                            <div class="text-center">
                                @if($avgRating)
                                    <p class="text-lg font-black text-gold">{{ $avgRating }}</p>
                                    <p class="text-[8px] uppercase tracking-widest text-muted">Rating</p>
                                @else
                                    <p class="text-lg font-black text-muted">—</p>
                                    <p class="text-[8px] uppercase tracking-widest text-muted">Rating</p>
                                @endif
                            </div>
                            <div class="text-center border-x border-white/5">
                                <p class="text-lg font-black text-white">{{ $citasTotal }}</p>
                                <p class="text-[8px] uppercase tracking-widest text-muted">Citas</p>
                            </div>
                            <div class="text-center">
                                <p class="text-lg font-black text-white">{{ $yearsExp }}a</p>
                                <p class="text-[8px] uppercase tracking-widest text-muted">Exp.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Info de cuenta --}}
                <div class="rounded-2xl border border-white/8 bg-[#111] p-5 space-y-4">
                    <p class="text-[9px] font-black uppercase tracking-widest text-muted">Cuenta</p>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[9px] text-gold/50 font-black uppercase tracking-wider mb-0.5">Email</p>
                            <p class="text-sm text-white font-bold truncate">{{ auth()->user()->email }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] text-gold/50 font-black uppercase tracking-wider mb-0.5">Miembro desde</p>
                            <p class="text-sm text-white font-bold">{{ auth()->user()->created_at->translatedFormat('F Y') }}</p>
                        </div>
                        <div>
                            <p class="text-[9px] text-gold/50 font-black uppercase tracking-wider mb-0.5">Citas este mes</p>
                            <p class="text-sm text-white font-bold">{{ $citasMes }}</p>
                        </div>
                    </div>
                </div>

                {{-- Navegación rápida --}}
                <div class="rounded-2xl border border-white/8 bg-[#111] p-5 space-y-2">
                    <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-3">Secciones</p>
                    <a href="{{ route('barber.agenda') }}" class="flex items-center gap-3 p-2.5 rounded-xl text-muted hover:text-white hover:bg-white/5 transition-all text-[11px] font-black uppercase tracking-wider">
                        <svg class="h-4 w-4 text-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Mi Agenda
                    </a>
                    <a href="{{ route('barber.schedule.edit') }}" class="flex items-center gap-3 p-2.5 rounded-xl text-muted hover:text-white hover:bg-white/5 transition-all text-[11px] font-black uppercase tracking-wider">
                        <svg class="h-4 w-4 text-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Mi Horario
                    </a>
                    <a href="{{ route('barber.portfolio.index') }}" class="flex items-center gap-3 p-2.5 rounded-xl text-muted hover:text-white hover:bg-white/5 transition-all text-[11px] font-black uppercase tracking-wider">
                        <svg class="h-4 w-4 text-gold shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Mi Portafolio
                    </a>
                </div>
            </aside>

            {{-- ── MAIN ─────────────────────────────────────── --}}
            <main class="lg:col-span-2 space-y-6">

                {{-- Formulario --}}
                <section class="ui-card-premium p-7">
                    <div class="mb-7 pb-5 border-b border-white/5 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-black text-white uppercase tracking-wide">Editar Perfil</h3>
                            <p class="text-[10px] text-muted mt-0.5">Actualiza tu bio y especialidades.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('barber.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf @method('PUT')

                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            {{-- Especialidades --}}
                            <div class="sm:col-span-2">
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Especialidades</label>
                                <input type="text" name="especialidades"
                                       value="{{ old('especialidades', $barber->especialidades) }}"
                                       class="h-11 w-full rounded-xl border border-white/10 bg-black/30 px-4 text-sm text-white placeholder-white/20 focus:border-gold/50 focus:outline-none transition-all"
                                       placeholder="Ej: Fade, Barba tradicional, Colorimetría...">
                                <p class="mt-1.5 text-[9px] text-muted/60 uppercase tracking-wider">Separa con comas</p>
                                @error('especialidades')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>

                            {{-- Biografía --}}
                            <div class="sm:col-span-2">
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Sobre mí</label>
                                <textarea name="descripcion" rows="4"
                                          class="w-full rounded-xl border border-white/10 bg-black/30 px-4 py-3 text-sm text-white placeholder-white/20 focus:border-gold/50 focus:outline-none transition-all leading-relaxed resize-none"
                                          placeholder="Cuéntales tu trayectoria y estilo de trabajo...">{{ old('descripcion', $barber->descripcion) }}</textarea>
                                @error('descripcion')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>

                            {{-- Foto --}}
                            <div class="sm:col-span-2" x-data="{ fileName: '' }">
                                <label class="text-[9px] font-black uppercase tracking-widest text-muted block mb-1.5">Foto de Perfil</label>
                                <label class="flex items-center gap-4 w-full rounded-xl border border-dashed border-white/10 bg-black/20 px-5 py-4 cursor-pointer hover:border-gold/30 hover:bg-black/30 transition-all group">
                                    <div class="h-10 w-10 rounded-xl bg-gold/10 border border-gold/20 flex items-center justify-center shrink-0 group-hover:bg-gold/20 transition-all">
                                        <svg class="h-5 w-5 text-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs font-bold text-white" x-text="fileName || 'Seleccionar imagen'"></p>
                                        <p class="text-[9px] text-muted uppercase tracking-wider mt-0.5">JPG, PNG, WEBP · Máx. 5MB · Recomendado 500×500</p>
                                    </div>
                                    <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="hidden"
                                           @change="fileName = $event.target.files[0]?.name || ''">
                                </label>
                                @error('foto')<p class="mt-1 text-[10px] font-black text-red-500 uppercase">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="pt-5 border-t border-white/5 flex items-center justify-between gap-4">
                            <a href="{{ route('barber.agenda') }}" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">Descartar</a>
                            <button type="submit" class="ui-btn px-10 py-3 text-[11px] uppercase tracking-[0.15em] shadow-lg shadow-gold/20">
                                Guardar Cambios &rarr;
                            </button>
                        </div>
                    </form>
                </section>

                {{-- ── GALERÍA CONECTADA AL PORTAFOLIO ──────── --}}
                <section class="ui-card-premium p-7">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-black text-white uppercase tracking-wide">Galería de <span class="text-gold">Trabajos</span></h3>
                            <p class="text-[10px] text-muted mt-0.5">{{ $portfolioTotal }} trabajo{{ $portfolioTotal !== 1 ? 's' : '' }} publicados en tu portafolio</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('barber.portfolio.create') }}"
                               class="inline-flex items-center gap-1.5 rounded-xl border border-gold/25 bg-gold/8 px-3.5 py-2 text-[10px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                Nuevo
                            </a>
                            @if($portfolioTotal > 6)
                                <a href="{{ route('barber.portfolio.index') }}"
                                   class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-muted hover:text-white transition">
                                    Ver todos ({{ $portfolioTotal }}) &rarr;
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($portfolioWorks->isEmpty())
                        <div class="rounded-xl border border-dashed border-white/10 py-14 text-center">
                            <svg class="h-12 w-12 text-white/5 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="text-sm font-bold text-muted uppercase tracking-widest">Sin trabajos publicados</p>
                            <a href="{{ route('barber.portfolio.create') }}" class="mt-3 inline-block text-[10px] font-black uppercase tracking-widest text-gold hover:text-white transition">
                                Publicar ahora &rarr;
                            </a>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach($portfolioWorks as $work)
                                <div class="group aspect-square rounded-xl overflow-hidden relative bg-[#0d0d0d] border border-white/5">
                                    @if($work->images->first())
                                        @php $workImg = $work->images->first()->image; @endphp
                                        <img src="{{ str_starts_with($workImg, 'http') ? $workImg : \Illuminate\Support\Facades\Storage::url($workImg) }}"
                                             class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500 grayscale-[15%] group-hover:grayscale-0"
                                             loading="lazy"
                                             alt="{{ $work->title }}">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center">
                                            <svg class="h-8 w-8 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                    @endif

                                    {{-- Overlay info --}}
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col justify-end p-3">
                                        <p class="text-[10px] font-black text-gold uppercase truncate">{{ $work->title }}</p>
                                        <div class="flex items-center gap-2 mt-1 text-[9px] text-white/60">
                                            <span class="flex items-center gap-1">
                                                <svg class="h-2.5 w-2.5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                                {{ $work->reactions->count() }}
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                {{ $work->comments->count() }}
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Multi-image badge --}}
                                    @if($work->images->count() > 1)
                                        <div class="absolute top-2 left-2 flex items-center gap-1 rounded-md bg-black/60 backdrop-blur-sm px-1.5 py-0.5">
                                            <svg class="h-2.5 w-2.5 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                            <span class="text-[8px] font-black text-white/60">{{ $work->images->count() }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- "Ver más" tile si hay más de 6 --}}
                            @if($portfolioTotal > 6)
                                <a href="{{ route('barber.portfolio.index') }}"
                                   class="aspect-square rounded-xl border border-dashed border-white/10 bg-black/20 flex flex-col items-center justify-center gap-2 hover:border-gold/30 hover:bg-gold/5 transition-all group">
                                    <svg class="h-7 w-7 text-muted group-hover:text-gold transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-muted group-hover:text-gold transition-colors">+{{ $portfolioTotal - 6 }} más</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </section>

            </main>
        </div>
    </div>
</x-app-layout>
