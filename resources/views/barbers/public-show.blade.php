<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $barber->user?->name }} — UrbanBlade</title>
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-white bg-[#0a0a0a]">
    <div class="relative min-h-screen">

        {{-- Nav --}}
        <nav class="sticky top-0 z-50 bg-black/80 backdrop-blur-md border-b border-white/5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <a href="/" class="flex items-center gap-3">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-9 w-9 object-contain">
                        <span class="text-lg font-black uppercase tracking-tighter">Urban<span class="text-gold">Blade</span></span>
                    </a>
                    <a href="/" class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                        Inicio
                    </a>
                </div>
            </div>
        </nav>

        <main class="py-16 sm:py-24">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-14 lg:grid-cols-12 items-start">

                    {{-- ── IZQUIERDA: Foto + stats ───────────────── --}}
                    <div class="lg:col-span-5 space-y-6">

                        {{-- Foto hero --}}
                        <div class="ui-card-premium p-0 overflow-hidden border-gold/20" style="box-shadow:0 0 60px rgba(212,175,55,0.08)">
                            <div class="aspect-[4/5] relative bg-[#0d0d0d]">
                                @php
                                    $defaultBarberImages = [
                                        'https://images.unsplash.com/photo-1605497788044-5a32c7078486?w=600&q=80&fit=crop',
                                        'https://images.unsplash.com/photo-1592647420148-bfcc177e2117?w=600&q=80&fit=crop',
                                        'https://images.unsplash.com/photo-1519345182560-3f2917c472ef?w=600&q=80&fit=crop',
                                        'https://images.unsplash.com/photo-1621605815971-fbc98d665033?w=600&q=80&fit=crop',
                                    ];
                                    $barberImgSeed = abs(crc32($barber->id ?? 'default')) % count($defaultBarberImages);
                                @endphp
                                @if($barber->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                                         class="h-full w-full object-cover"
                                         alt="{{ $barber->user?->name }}">
                                @else
                                    <img src="{{ $defaultBarberImages[$barberImgSeed] }}"
                                         class="h-full w-full object-cover opacity-80"
                                         alt="{{ $barber->user?->name }}"
                                         loading="lazy">
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent"></div>
                                <div class="absolute bottom-6 left-6 right-6">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/30 bg-gold/10 px-3 py-1 text-[9px] font-black uppercase tracking-widest text-gold mb-2">
                                        Maestro Titulado
                                    </span>
                                    <h1 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tighter leading-none">{{ $barber->user?->name }}</h1>
                                    @if($barber->especialidades)
                                        <p class="text-[11px] font-bold text-gold/70 uppercase tracking-widest mt-1.5">
                                            {{ explode(',', $barber->especialidades)[0] ?? '' }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Stats reales --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div class="ui-card-premium p-4 text-center">
                                @if($avgRating)
                                    <p class="text-2xl font-black text-gold">{{ $avgRating }}</p>
                                @else
                                    <p class="text-2xl font-black text-muted">—</p>
                                @endif
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest mt-1">Rating</p>
                            </div>
                            <div class="ui-card-premium p-4 text-center">
                                <p class="text-2xl font-black text-white">{{ $citasCompletadas > 999 ? floor($citasCompletadas/100)/10 .'k' : $citasCompletadas }}</p>
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest mt-1">Cortes</p>
                            </div>
                            <div class="ui-card-premium p-4 text-center">
                                <p class="text-2xl font-black text-white">{{ $yearsExp }}<span class="text-gold text-lg">a</span></p>
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest mt-1">Exp.</p>
                            </div>
                        </div>

                        {{-- Disponibilidad --}}
                        <div class="rounded-2xl border {{ $disponibleHoy ? 'border-emerald-500/25 bg-emerald-500/5' : 'border-white/8 bg-[#111]' }} px-5 py-4 flex items-center gap-4">
                            <div class="h-2.5 w-2.5 rounded-full {{ $disponibleHoy ? 'bg-emerald-400 animate-pulse' : 'bg-white/20' }} shrink-0"></div>
                            <div>
                                <p class="text-sm font-black {{ $disponibleHoy ? 'text-emerald-300' : 'text-muted' }}">
                                    {{ $disponibleHoy ? 'Disponible hoy' : 'No disponible hoy' }}
                                </p>
                                <p class="text-[10px] text-muted mt-0.5">{{ now()->translatedFormat('l, d \d\e F') }}</p>
                            </div>
                            @if($disponibleHoy)
                                <a href="{{ route('register') }}" class="ml-auto text-[10px] font-black uppercase tracking-widest text-emerald-400 hover:text-white transition">
                                    Agendar &rarr;
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- ── DERECHA: Bio + skills + portafolio + CTA ──── --}}
                    <div class="lg:col-span-7 space-y-12">

                        {{-- Bio --}}
                        <section>
                            <p class="text-[9px] font-black text-gold uppercase tracking-[0.3em] mb-4">Sobre el maestro</p>
                            <p class="text-lg font-medium text-white/80 leading-relaxed">
                                {{ $barber->descripcion ?: 'Especialista dedicado a esculpir la mejor versión de cada caballero que se sienta en su estación, combinando técnicas vanguardistas con el arte clásico del afeitado.' }}
                            </p>
                        </section>

                        {{-- Especialidades --}}
                        @if($barber->especialidades)
                        <section>
                            <p class="text-[9px] font-black text-gold uppercase tracking-[0.3em] mb-5">Especialidades</p>
                            <div class="flex flex-wrap gap-3">
                                @foreach(explode(',', $barber->especialidades) as $skill)
                                    <span class="px-4 py-2.5 rounded-2xl border border-white/10 bg-white/5 text-sm font-bold text-white uppercase tracking-widest hover:border-gold/40 hover:bg-gold/5 hover:text-gold transition-all cursor-default">
                                        {{ trim($skill) }}
                                    </span>
                                @endforeach
                            </div>
                        </section>
                        @endif

                        {{-- Portafolio --}}
                        <section>
                            <div class="flex items-center justify-between mb-6">
                                <p class="text-[9px] font-black text-gold uppercase tracking-[0.3em]">Portafolio de trabajos</p>
                                <span class="text-[10px] text-muted">{{ $barber->works->count() }} publicado{{ $barber->works->count() !== 1 ? 's' : '' }}</span>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @forelse($barber->works as $work)
                                    <div class="aspect-square rounded-2xl overflow-hidden border border-white/5 bg-white/3 relative group">
                                        @if($work->images->first())
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($work->images->first()->image) }}"
                                                 class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                 loading="lazy"
                                                 alt="{{ $work->title }}">
                                        @else
                                            <div class="h-full w-full flex items-center justify-center bg-white/5">
                                                <svg class="h-10 w-10 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            </div>
                                        @endif
                                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-4">
                                            <span class="flex items-center gap-1 text-xs font-black text-white">
                                                <svg class="h-3.5 w-3.5 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                                {{ $work->reactions->count() }}
                                            </span>
                                            <span class="flex items-center gap-1 text-xs font-black text-white">
                                                <svg class="h-3.5 w-3.5 text-white/60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                                                {{ $work->comments->count() }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-full py-12 text-center border border-dashed border-white/8 rounded-2xl">
                                        <p class="text-sm text-muted italic">Sin trabajos publicados aún.</p>
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        {{-- CTA --}}
                        <section class="pt-8 border-t border-white/8">
                            <div class="ui-card-premium p-8 border-gold/25 relative overflow-hidden">
                                <div class="absolute right-0 top-0 h-full w-1/2 bg-gradient-to-l from-gold/8 to-transparent pointer-events-none"></div>
                                <div class="relative z-10">
                                    <h3 class="text-2xl font-black text-white uppercase tracking-tighter">¿Listo para un cambio?</h3>
                                    <p class="mt-2 text-sm text-muted">
                                        Reserva tu lugar en la agenda del Maestro {{ explode(' ', $barber->user?->name ?? '')[0] }}.
                                    </p>
                                    <div class="mt-7 flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                                        <a href="{{ route('register') }}" class="ui-btn px-8 py-3.5 text-[11px] uppercase tracking-[0.15em]">
                                            Agendar Ahora
                                        </a>
                                        @if($disponibleHoy)
                                            <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                                <span class="text-[10px] font-black uppercase tracking-widest">Disponible hoy</span>
                                            </div>
                                        @else
                                            <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-white/5 text-muted border border-white/10">
                                                <span class="h-2 w-2 rounded-full bg-white/20"></span>
                                                <span class="text-[10px] font-black uppercase tracking-widest">Consulta disponibilidad</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </section>

                    </div>
                </div>
            </div>
        </main>

        <footer class="border-t border-white/5 py-10 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-muted">
                UrbanBlade Grooming Studio &bull; {{ date('Y') }}
            </p>
        </footer>
    </div>
</body>
</html>
