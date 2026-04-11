<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'BarberPro') }} - Premium Grooming Studio</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />
    
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-white bg-[#0a0a0a]">
    <div class="relative min-h-screen">
        <!-- Navigation -->
        <nav x-data="{ mobileMenuOpen: false }" class="sticky top-0 z-50 bg-black/90 backdrop-blur-xl border-b border-white/5">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-20 items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-gold to-gold-dim shadow-lg shadow-gold/20">
                            <svg class="h-6 w-6 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <a href="/" class="text-xl font-black uppercase tracking-tighter">Barber<span class="text-gold">Pro</span></a>
                    </div>

                    <div class="hidden md:flex items-center gap-8 text-[10px] font-black uppercase tracking-[0.2em] text-muted">
                        <a href="#inicio" class="hover:text-gold transition-colors">Inicio</a>
                        <a href="{{ route('services.public.index') }}" class="hover:text-gold transition-colors">Servicios</a>
                        <a href="#equipo" class="hover:text-gold transition-colors">Maestros</a>
                        <a href="#contacto" class="hover:text-gold transition-colors">Ubicación</a>
                        <div class="h-4 w-px bg-white/10 mx-2"></div>
                        @auth
                            <a href="{{ route('dashboard') }}" class="ui-btn py-2 px-6 shadow-none">Mi Panel</a>
                        @else
                            <a href="{{ route('login') }}" class="hover:text-gold transition-colors">Acceso</a>
                            <a href="{{ route('register') }}" class="ui-btn py-2 px-6 shadow-gold/10">Reservar</a>
                        @endauth
                    </div>

                    <!-- Mobile Menu Button -->
                    <div class="md:hidden">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-white p-2">
                            <svg x-show="!mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/></svg>
                            <svg x-show="mobileMenuOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile Menu -->
            <div x-show="mobileMenuOpen" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-4"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="md:hidden bg-black/95 border-b border-white/5 px-4 pt-2 pb-6 space-y-4"
                 style="display: none;">
                <a href="#inicio" @click="mobileMenuOpen = false" class="block text-xs font-black uppercase tracking-widest text-white py-2">Inicio</a>
                <a href="{{ route('services.public.index') }}" @click="mobileMenuOpen = false" class="block text-xs font-black uppercase tracking-widest text-white py-2">Servicios</a>
                <a href="#equipo" @click="mobileMenuOpen = false" class="block text-xs font-black uppercase tracking-widest text-white py-2">Maestros</a>
                <a href="#contacto" @click="mobileMenuOpen = false" class="block text-xs font-black uppercase tracking-widest text-white py-2">Ubicación</a>
                <div class="pt-4 flex flex-col gap-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="ui-btn w-full py-3">Mi Panel</a>
                    @else
                        <a href="{{ route('login') }}" class="text-center text-xs font-black uppercase tracking-widest text-muted py-2">Acceso</a>
                        <a href="{{ route('register') }}" class="ui-btn w-full py-3">Reservar Ahora</a>
                    @endauth
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <header id="inicio" class="relative flex items-center justify-center overflow-hidden min-h-[90vh]">
            <div class="absolute inset-0 z-0 bg-[url('https://images.unsplash.com/photo-1503951914875-452162b0f3f1?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-30 scale-105 animate-pulse"></div>
            <div class="absolute inset-0 z-1 bg-gradient-to-b from-black/60 via-transparent to-[#0a0a0a]"></div>
            
            <div class="relative z-10 mx-auto max-w-7xl px-4 text-center">
                <span class="ui-badge mb-8 animate-bounce border-gold/40 bg-gold/10 text-gold px-6 py-2">Tradición & Vanguardia</span>
                <h1 class="text-6xl font-black tracking-tighter sm:text-8xl lg:text-9xl uppercase leading-[0.9]">
                    LA <span class="text-gradient-gold">EXCELENCIA</span> <br> <span class="ui-title-serif lowercase italic text-5xl sm:text-7xl lg:text-8xl normal-case opacity-90">en cada detalle</span>
                </h1>
                <p class="mx-auto mt-10 max-w-2xl text-lg text-muted font-medium leading-relaxed">
                    Elevamos el concepto de barbería a un estudio de arte. Un espacio diseñado para el hombre que exige perfección y confort.
                </p>
                <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-6">
                    <a href="{{ route('register') }}" class="ui-btn w-full sm:w-auto px-12 py-5 text-base shadow-[0_0_50px_rgba(212,175,55,0.2)]">
                        Agendar Cita Premium
                    </a>
                    <a href="{{ route('services.public.index') }}" class="ui-btn-secondary w-full sm:w-auto px-12 py-5 text-base border-white/10 hover:border-gold/50">
                        Nuestros Servicios
                    </a>
                </div>
            </div>
        </header>

        <!-- Dynamic Services Section -->
        <section id="servicios" class="py-32 bg-[#0d0d0d] relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-px bg-gradient-to-r from-transparent via-gold/20 to-transparent"></div>
            
            <div class="mx-auto max-w-7xl px-4">
                <div class="mb-20 text-center">
                    <h2 class="text-4xl font-black tracking-tight uppercase">Servicios <span class="text-gold">Signature</span></h2>
                    <p class="mt-4 text-muted uppercase tracking-widest text-[10px] font-bold">Maestría artesanal para tu estilo personal</p>
                    <div class="mt-6 h-1 w-24 bg-gold mx-auto rounded-full shadow-[0_0_15px_rgba(212,175,55,0.5)]"></div>
                </div>

                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    @forelse($services as $service)
                        <article class="ui-card-premium p-10 group hover:border-gold/40">
                            <div class="mb-8 h-14 w-14 rounded-2xl bg-gold/5 border border-gold/10 text-gold flex items-center justify-center group-hover:scale-110 group-hover:bg-gold group-hover:text-black transition-all duration-500">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121" />
                                </svg>
                            </div>
                            <h3 class="text-2xl font-black text-white uppercase">{{ $service->nombre }}</h3>
                            <p class="mt-4 text-sm text-muted font-medium leading-relaxed">{{ $service->descripcion ?: 'Una experiencia diseñada para resaltar tu mejor versión con técnica clásica.' }}</p>
                            <div class="mt-8 flex items-center justify-between">
                                <span class="text-xl font-black text-white">${{ number_format($service->precio, 2) }}</span>
                                <span class="text-[10px] font-black uppercase tracking-widest text-gold bg-gold/5 px-3 py-1 rounded-full border border-gold/10">{{ $service->duracion_min }} Min</span>
                            </div>
                        </article>
                    @empty
                        <div class="col-span-3 text-center py-20 text-muted italic">Cargando catálogo premium...</div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Team / Barbers Section -->
        <section id="equipo" class="py-32 relative">
            <div class="mx-auto max-w-7xl px-4">
                <div class="mb-20 flex flex-col md:flex-row md:items-end justify-between gap-8">
                    <div>
                        <h2 class="text-4xl font-black tracking-tight uppercase">Los <span class="text-gold">Maestros</span></h2>
                        <p class="mt-2 text-muted uppercase tracking-widest text-[10px] font-bold">Arquitectos de la imagen masculina</p>
                    </div>
                    <a href="{{ route('register') }}" class="text-[10px] font-black uppercase tracking-[0.2em] text-gold hover:text-white transition-colors flex items-center gap-2">
                        Ver todo el equipo <span class="text-lg">&rarr;</span>
                    </a>
                </div>

                <div class="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
                    @forelse($barbers as $barber)
                        <a href="{{ route('barbers.public.show', $barber) }}" class="group relative block">
                            <div class="aspect-[3/4] overflow-hidden rounded-3xl border border-white/5 bg-[#111] relative">
                                @if($barber->foto)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::url($barber->foto) }}" class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 group-hover:scale-110">
                                @else
                                    <div class="h-full w-full flex items-center justify-center bg-white/5">
                                        <svg class="h-20 w-20 text-white/5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" stroke-width="1"/></svg>
                                    </div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-80"></div>
                                <div class="absolute bottom-6 left-6 right-6">
                                    <h4 class="text-lg font-black text-white uppercase">{{ $barber->user?->name }}</h4>
                                    <p class="text-[9px] font-bold text-gold uppercase tracking-widest mt-1">{{ $barber->especialidades ?: 'Master Groomer' }}</p>
                                </div>
                            </div>
                        </a>
                    @empty
                        <div class="col-span-4 text-center py-20 text-muted">Nuestros maestros se están preparando...</div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="py-32 bg-gradient-to-b from-[#0a0a0a] to-[#0d0d0d] border-y border-white/5">
            <div class="mx-auto max-w-7xl px-4">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">
                    <div>
                        <h2 class="text-4xl font-black tracking-tight uppercase leading-[1.1]">Lo que dicen <br> nuestros <span class="text-gradient-gold">Caballeros</span></h2>
                        <p class="mt-6 text-muted text-lg leading-relaxed">Nuestra reputación se ha forjado con precisión y satisfacción. Únete a la comunidad BarberPro.</p>
                        <div class="mt-10 flex gap-4">
                            <div class="flex -space-x-3">
                                @for($i=1; $i<=4; $i++)
                                    <div class="h-12 w-12 rounded-full border-4 border-[#0a0a0a] bg-[#222]"></div>
                                @endfor
                            </div>
                            <div class="flex flex-col justify-center">
                                <p class="text-sm font-black text-white">500+ Reseñas</p>
                                <div class="flex text-gold text-xs">★★★★★</div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="ui-card-premium p-8 border-gold/20 bg-gold/5 translate-x-4">
                            <p class="text-lg font-serif italic text-white leading-relaxed">"La atención al detalle es increíble. No es solo un corte, es un ritual de relajación. Totalmente recomendado."</p>
                            <p class="mt-6 text-[10px] font-black uppercase tracking-widest text-gold">&mdash; Ricardo Arjona, Cliente VIP</p>
                        </div>
                        <div class="ui-card-premium p-8 border-white/5">
                            <p class="text-lg font-serif italic text-white leading-relaxed">"El sistema de reservas es súper rápido. Llego y mi barbero ya me está esperando. Eficiencia y lujo en un solo lugar."</p>
                            <p class="mt-6 text-[10px] font-black uppercase tracking-widest text-muted">&mdash; Julian Casas, Emprendedor</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact / Location -->
        <section id="contacto" class="py-32 relative overflow-hidden">
            <div class="mx-auto max-w-7xl px-4 relative z-10">
                <div class="ui-surface p-0 overflow-hidden border-white/5">
                    <div class="flex flex-col lg:flex-row">
                        <div class="p-12 lg:w-1/3 bg-panel/50 backdrop-blur-md">
                            <h3 class="text-2xl font-black text-white uppercase tracking-tighter mb-8">Visítanos</h3>
                            <div class="space-y-8">
                                <div class="flex gap-4">
                                    <div class="h-10 w-10 rounded-xl bg-gold/10 text-gold flex items-center justify-center shrink-0"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" stroke-width="2"/></svg></div>
                                    <div><p class="text-[10px] font-black text-gold uppercase tracking-widest">Ubicación</p><p class="text-sm text-white mt-1 font-medium">Av. de la Reforma 123, <br>Suite 405, CDMX</p></div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="h-10 w-10 rounded-xl bg-gold/10 text-gold flex items-center justify-center shrink-0"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" stroke-width="2"/></svg></div>
                                    <div><p class="text-[10px] font-black text-gold uppercase tracking-widest">Contacto</p><p class="text-sm text-white mt-1 font-medium">+52 55 1234 5678 <br>hola@barberpro.com</p></div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="h-10 w-10 rounded-xl bg-gold/10 text-gold flex items-center justify-center shrink-0"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg></div>
                                    <div><p class="text-[10px] font-black text-gold uppercase tracking-widest">Horario</p><p class="text-sm text-white mt-1 font-medium">Lun - Sáb: 9:00 - 21:00 <br>Dom: Cerrado</p></div>
                                </div>
                            </div>
                        </div>
                        <div class="lg:w-2/3 h-[400px] lg:h-auto bg-[#111] relative">
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <svg class="h-16 w-16 text-white/5 mx-auto mb-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-muted">Mapa Interactivo en Alta Resolución</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="py-32 bg-gradient-to-t from-gold/10 to-transparent">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h2 class="text-5xl font-black text-white uppercase tracking-tighter leading-none mb-10">¿Listo para transformar <br> <span class="text-gold">tu mejor versión?</span></h2>
                <a href="{{ route('register') }}" class="ui-btn px-16 py-6 text-lg uppercase tracking-[0.3em] gold-glow-hover animate-float">
                    Reserva tu Turno Ahora
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/5 bg-[#050505] py-20 relative overflow-hidden">
            <div class="absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-gold/5 blur-3xl"></div>
            
            <div class="mx-auto max-w-7xl px-4">
                <div class="flex flex-col md:flex-row justify-between items-center gap-12">
                    <div class="flex flex-col items-center md:items-start">
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-lg bg-gold text-black flex items-center justify-center">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <span class="text-lg font-black uppercase tracking-tighter text-white">Barber<span class="text-gold">Pro</span></span>
                        </div>
                        <p class="mt-6 text-sm text-muted max-w-xs text-center md:text-left leading-relaxed">
                            Líderes en el arte del grooming masculino. Unimos tradición y tecnología para ofrecerte una experiencia inigualable.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-16">
                        <div>
                            <h5 class="text-[10px] font-black uppercase tracking-widest text-white mb-6">Navegación</h5>
                            <ul class="space-y-4 text-xs font-bold text-muted uppercase tracking-widest">
                                <li><a href="#servicios" class="hover:text-gold transition">Servicios</a></li>
                                <li><a href="#equipo" class="hover:text-gold transition">Maestros</a></li>
                                <li><a href="{{ route('login') }}" class="hover:text-gold transition">Acceso Staff</a></li>
                            </ul>
                        </div>
                        <div>
                            <h5 class="text-[10px] font-black uppercase tracking-widest text-white mb-6">Social</h5>
                            <div class="flex gap-4">
                                <a href="#" class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:bg-gold/10 transition-all"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>
                                <a href="#" class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:bg-gold/10 transition-all"><svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-20 pt-10 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                    <p class="text-[9px] font-black uppercase tracking-[0.3em] text-muted">
                        &copy; 2026 BarberPro Elite Grooming Studio. Todos los derechos reservados.
                    </p>
                    <div class="flex gap-8 text-[9px] font-black uppercase tracking-[0.2em] text-muted">
                        <a href="#" class="hover:text-white transition">Privacidad</a>
                        <a href="#" class="hover:text-white transition">Términos</a>
                    </div>
                </div>
            </div>
        </footer>
        <div class="fixed bottom-6 right-6 z-50">
            <x-chatbot />
        </div>
    </div>
</body>
</html>
