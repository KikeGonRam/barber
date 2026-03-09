<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Maestro {{ $barber->user?->name }} - BarberPro Elite</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-white bg-[#0a0a0a]">
    <div class="relative min-h-screen">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 bg-black/80 backdrop-blur-md border-b border-white/5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <a href="/" class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gold shadow-lg shadow-gold/20">
                            <svg class="h-6 w-6 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="text-xl font-black uppercase tracking-tighter">Barber<span class="text-gold">Pro</span></span>
                    </a>
                    <a href="/" class="text-[10px] font-black uppercase tracking-widest text-muted hover:text-gold transition">&larr; Volver al Inicio</a>
                </div>
            </div>
        </nav>

        <main class="py-24">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-start">
                    
                    <!-- Left: Profile Image & Stats -->
                    <div class="lg:col-span-5 space-y-8">
                        <div class="ui-card-premium p-0 overflow-hidden border-gold/20 shadow-2xl shadow-gold/5 animate-float">
                            <div class="aspect-[4/5] relative">
                                @if($barber->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}" class="h-full w-full object-cover">
                                @else
                                    <div class="h-full w-full flex items-center justify-center bg-white/5">
                                        <svg class="h-32 w-32 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="1"/></svg>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent"></div>
                                <div class="absolute bottom-8 left-8">
                                    <span class="ui-badge border-gold/40 bg-gold/10 text-gold mb-2">Maestro Titulado</span>
                                    <h1 class="text-4xl font-black text-white uppercase tracking-tighter">{{ $barber->user?->name }}</h1>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="ui-card-premium p-4 text-center border-white/5 bg-white/5">
                                <p class="text-2xl font-black text-gold">4.9</p>
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest">Rating</p>
                            </div>
                            <div class="ui-card-premium p-4 text-center border-white/5 bg-white/5">
                                <p class="text-2xl font-black text-white">500+</p>
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest">Cortes</p>
                            </div>
                            <div class="ui-card-premium p-4 text-center border-white/5 bg-white/5">
                                <p class="text-2xl font-black text-white">8y</p>
                                <p class="text-[8px] uppercase font-black text-muted tracking-widest">Exp.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Info & Bio -->
                    <div class="lg:col-span-7 space-y-12">
                        <section>
                            <h2 class="text-xs font-black text-gold uppercase tracking-[0.3em] mb-4">Sobre el Maestro</h2>
                            <p class="text-xl font-medium text-white leading-relaxed">
                                {{ $barber->descripcion ?: 'Especialista en técnicas vanguardistas y clásicas, dedicado a esculpir la mejor versión de cada caballero que se sienta en su estación.' }}
                            </p>
                        </section>

                        <section>
                            <h2 class="text-xs font-black text-gold uppercase tracking-[0.3em] mb-6">Especialidades</h2>
                            <div class="flex flex-wrap gap-3">
                                @php $skills = explode(',', $barber->especialidades ?: 'Corte Clásico, Barba Tradicional, Fade Moderno'); @endphp
                                @foreach($skills as $skill)
                                    <span class="px-5 py-3 rounded-2xl border border-white/10 bg-white/5 text-sm font-bold text-white uppercase tracking-widest hover:border-gold/50 transition-colors cursor-default">
                                        {{ trim($skill) }}
                                    </span>
                                @endforeach
                            </div>
                        </section>

                        <!-- Portfolio Gallery (Instagram Style) -->
                        <section>
                            <h2 class="text-xs font-black text-gold uppercase tracking-[0.3em] mb-8">Portafolio de Trabajos</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                @forelse($barber->works as $work)
                                    <a href="{{ route('social.feed') }}#work-{{ $work->id }}" class="aspect-square rounded-2xl overflow-hidden border border-white/5 bg-white/5 relative group">
                                        @if($work->images->first())
                                            <img src="{{ \Illuminate\Support\Facades\Storage::url($work->images->first()->image) }}" class="h-full w-full object-cover group-hover:scale-110 transition-transform duration-500">
                                        @endif
                                        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-4">
                                            <span class="flex items-center gap-1 text-xs font-black text-white">
                                                <svg class="h-4 w-4 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                                {{ $work->reactions->count() }}
                                            </span>
                                            <span class="flex items-center gap-1 text-xs font-black text-white">
                                                <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z"/></svg>
                                                {{ $work->comments->count() }}
                                            </span>
                                        </div>
                                    </a>
                                @empty
                                    <div class="col-span-full py-12 text-center border border-dashed border-white/5 rounded-3xl text-muted italic text-sm">
                                        El maestro aún no ha compartido trabajos visuales.
                                    </div>
                                @endforelse
                            </div>
                        </section>

                        <section class="pt-8 border-t border-white/10">
                            <div class="ui-card-premium p-10 border-gold/30 gold-glow relative overflow-hidden group">
                                <div class="absolute right-0 top-0 h-full w-1/2 bg-gradient-to-l from-gold/10 to-transparent"></div>
                                <div class="relative z-10 text-center sm:text-left">
                                    <h3 class="text-2xl font-black text-white uppercase tracking-tighter">¿Listo para un cambio?</h3>
                                    <p class="mt-2 text-muted text-sm font-medium">Reserva tu lugar en la agenda del Maestro {{ explode(' ', $barber->user?->name)[0] }}.</p>
                                    <div class="mt-8 flex flex-col sm:flex-row gap-4">
                                        <a href="{{ route('register') }}" class="ui-btn px-10 py-4 text-xs">Agendar Ahora</a>
                                        <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-green-500/10 text-green-400 border border-green-500/20">
                                            <span class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
                                            <span class="text-[10px] font-black uppercase tracking-widest">Disponible Hoy</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 py-12 text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-muted">
                BarberPro Elite Grooming Studio &bull; {{ date('Y') }}
            </p>
        </footer>
    </div>
</body>
</html>
