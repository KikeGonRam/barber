<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    <title>{{ config('app.name') }} - Premium Grooming Studio</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />

    @safeVite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Grain overlay */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            opacity: 0.025;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            background-size: 200px 200px;
        }

        /* Reveal on scroll */
        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.65s ease, transform 0.65s ease;
        }
        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Parallax */
        .hero-bg { will-change: transform; }

        /* Badge float */
        @keyframes floatBadge {
            0%,100% { transform: translateY(0); }
            50%      { transform: translateY(-6px); }
        }
        .float-badge { animation: floatBadge 4s ease-in-out infinite; }

        /* Gold line grow */
        @keyframes lineGrow {
            from { width: 0; opacity: 0; }
            to   { width: 4rem; opacity: 1; }
        }
        .line-grow { animation: lineGrow 0.8s ease-out 0.2s forwards; width: 0; opacity: 0; }

        /* Barber card overlay */
        .barber-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 1.5rem;
            background: linear-gradient(to top, rgba(212,175,55,0.22), transparent 55%);
            opacity: 0;
            transition: opacity 0.5s;
            pointer-events: none;
        }
        .barber-card:hover::after { opacity: 1; }

        /* Step connector */
        .step-connector {
            position: absolute;
            top: 2rem;
            left: calc(50% + 2rem);
            right: calc(-50% + 2rem);
            height: 1px;
            background: linear-gradient(to right, rgba(212,175,55,0.3), rgba(212,175,55,0.05));
        }

        /* Scroll indicator */
        @keyframes scrollPulse {
            0%   { opacity: 0.5; transform: scaleY(1); }
            50%  { opacity: 1;   transform: scaleY(1.15); }
            100% { opacity: 0.5; transform: scaleY(1); }
        }
        .scroll-line { animation: scrollPulse 2s ease-in-out infinite; }
    </style>
</head>

<body class="font-sans antialiased text-white bg-[#0a0a0a]">
<div class="relative min-h-screen">

    <!-- ══════════════════════════════════════════ -->
    <!-- NAVBAR                                     -->
    <!-- ══════════════════════════════════════════ -->
    <nav x-data="{ open: false, scrolled: false }"
        x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 50, { passive: true })"
        :class="scrolled ? 'bg-black/95 shadow-[0_1px_0_rgba(212,175,55,0.1)]' : 'bg-black/60'"
        class="sticky top-0 z-50 backdrop-blur-xl border-b border-white/5 transition-all duration-500">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-20 items-center justify-between">

                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-10 w-10 object-contain">
                    <a href="/" class="text-xl font-black uppercase tracking-tighter">Urban<span class="text-gold">Blade</span></a>
                </div>

                <div class="hidden md:flex items-center gap-8 text-[10px] font-black uppercase tracking-[0.2em] text-muted">
                    <a href="#inicio"        class="hover:text-gold transition-colors">Inicio</a>
                    <a href="{{ route('services.public.index') }}" class="hover:text-gold transition-colors">Servicios</a>
                    <a href="#como-funciona" class="hover:text-gold transition-colors">Proceso</a>
                    <a href="#equipo"        class="hover:text-gold transition-colors">Maestros</a>
                    <a href="#contacto"      class="hover:text-gold transition-colors">Ubicación</a>
                    <div class="h-4 w-px bg-white/10 mx-1"></div>
                    @auth
                        <a href="{{ route('dashboard') }}" class="ui-btn py-2 px-6">Mi Panel</a>
                    @else
                        <a href="{{ route('login') }}"    class="hover:text-gold transition-colors">Acceso</a>
                        <a href="{{ route('register') }}"
                            class="ui-btn group py-2.5 px-7 text-[11px] font-black uppercase tracking-[0.15em] shadow-[0_0_25px_rgba(212,175,55,0.4)]">
                            Reservar
                            <svg class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                        </a>
                    @endauth
                </div>

                <button @click="open = !open" class="md:hidden text-white p-2" aria-label="Abrir menú">
                    <svg x-show="!open" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 6h16M4 12h16M4 18h16" stroke-width="2" stroke-linecap="round"/></svg>
                    <svg x-show="open"  class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display:none;"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
        </div>

        <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-3"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="md:hidden bg-black/98 border-b border-white/5 px-5 pt-2 pb-6 space-y-4"
            style="display:none;">
            <a href="#inicio"   @click="open=false" class="block text-[11px] font-black uppercase tracking-widest text-white py-2">Inicio</a>
            <a href="{{ route('services.public.index') }}" @click="open=false" class="block text-[11px] font-black uppercase tracking-widest text-white py-2">Servicios</a>
            <a href="#como-funciona" @click="open=false" class="block text-[11px] font-black uppercase tracking-widest text-white py-2">Proceso</a>
            <a href="#equipo"   @click="open=false" class="block text-[11px] font-black uppercase tracking-widest text-white py-2">Maestros</a>
            <a href="#contacto" @click="open=false" class="block text-[11px] font-black uppercase tracking-widest text-white py-2">Ubicación</a>
            <div class="pt-3 flex flex-col gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="ui-btn w-full py-3">Mi Panel</a>
                @else
                    <a href="{{ route('login') }}"    class="text-center text-[11px] font-black uppercase tracking-widest text-muted py-2">Acceso</a>
                    <a href="{{ route('register') }}" class="ui-btn w-full py-3">Reservar Ahora</a>
                @endauth
            </div>
        </div>
    </nav>

    <!-- ══════════════════════════════════════════ -->
    <!-- HERO                                       -->
    <!-- ══════════════════════════════════════════ -->
    <header id="inicio" class="relative flex items-center justify-center overflow-hidden" style="min-height: 92vh;">
        <!-- Foto de fondo -->
        <div class="hero-bg absolute inset-0 z-0 scale-110">
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1503951914875-452162b0f3f1?q=80&w=2070&auto=format&fit=crop')] bg-cover bg-center opacity-25"></div>
        </div>
        <!-- Overlays -->
        <div class="absolute inset-0 z-1 bg-gradient-to-b from-black/65 via-[#0a0a0a]/20 to-[#0a0a0a]"></div>
        <div class="absolute inset-0 z-1 bg-gradient-to-r from-[#0a0a0a]/50 via-transparent to-[#0a0a0a]/50"></div>

        <!-- Partículas -->
        <div class="absolute inset-0 z-2 pointer-events-none overflow-hidden">
            <span class="absolute top-[18%]  left-[14%]  h-1.5 w-1.5 rounded-full bg-gold/40 animate-ping" style="animation-duration:3.2s"></span>
            <span class="absolute top-[35%]  left-[7%]   h-1   w-1   rounded-full bg-gold/25 animate-ping" style="animation-duration:4.5s;animation-delay:.8s"></span>
            <span class="absolute top-[50%]  right-[10%] h-2   w-2   rounded-full bg-gold/20 animate-ping" style="animation-duration:5s;animation-delay:.4s"></span>
            <span class="absolute top-[64%]  left-[28%]  h-1   w-1   rounded-full bg-gold/30 animate-ping" style="animation-duration:3.8s;animation-delay:2s"></span>
            <span class="absolute top-[22%]  right-[22%] h-1.5 w-1.5 rounded-full bg-white/10 animate-ping" style="animation-duration:6s;animation-delay:1.5s"></span>
        </div>

        <div class="relative z-10 mx-auto max-w-7xl px-4 text-center">
            <!-- Badge -->
            <div class="inline-flex float-badge mb-8" data-hero="badge">
                <span class="ui-badge border-gold/35 bg-gold/8 text-gold px-5 py-2 text-[10px] tracking-[0.25em]">
                    <span class="h-1.5 w-1.5 rounded-full bg-gold animate-pulse mr-2 inline-block"></span>
                    Reservas Disponibles Hoy
                </span>
            </div>

            <!-- Headline -->
            <h1 class="font-black tracking-tighter uppercase leading-[0.88]"
                style="font-size: clamp(3.5rem, 11vw, 9rem);" data-hero="title">
                LA <span class="text-gradient-gold">EXCELENCIA</span>
            </h1>
            <p class="ui-title-serif lowercase italic opacity-90"
                style="font-size: clamp(2.5rem, 7vw, 6.5rem);" data-hero="subtitle">
                en cada detalle
            </p>

            <p class="mx-auto mt-10 max-w-xl text-lg text-muted font-medium leading-relaxed" data-hero="desc">
                Elevamos el concepto de barbería a un estudio de arte. Un espacio diseñado para el hombre que exige perfección y confort.
            </p>

            <div class="mt-12 flex flex-col sm:flex-row items-center justify-center gap-5" data-hero="cta">
                <a href="{{ route('register') }}" class="ui-btn w-full sm:w-auto px-12 py-5 text-[13px] tracking-[0.15em] shadow-[0_0_50px_rgba(212,175,55,0.18)]">
                    Agendar Cita Premium
                    <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                <a href="{{ route('services.public.index') }}" class="ui-btn-secondary w-full sm:w-auto px-12 py-5 text-[13px] tracking-[0.15em] border-white/10 hover:border-gold/50">
                    Ver Servicios
                </a>
            </div>

            <!-- Scroll hint -->
            <div class="mt-16 flex flex-col items-center gap-2 opacity-40" data-hero="scroll">
                <span class="text-[9px] font-black uppercase tracking-[0.4em] text-muted">Descubrir</span>
                <div class="scroll-line h-12 w-px bg-gradient-to-b from-gold/60 to-transparent"></div>
            </div>
        </div>
    </header>

    <!-- ══════════════════════════════════════════ -->
    <!-- STATS                                      -->
    <!-- ══════════════════════════════════════════ -->
    <section class="py-16 bg-[#0d0d0d] border-y border-white/5 relative overflow-hidden">
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/20 to-transparent"></div>

        <div class="mx-auto max-w-7xl px-4">
            <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                <div class="reveal text-center" style="transition-delay:0ms;">
                    <p class="text-4xl font-black text-white">500<span class="text-gold">+</span></p>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-muted">Clientes Satisfechos</p>
                </div>
                <div class="reveal text-center" style="transition-delay:100ms;">
                    <p class="text-4xl font-black text-white">10<span class="text-gold">+</span></p>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-muted">Años de Experiencia</p>
                </div>
                <div class="reveal text-center" style="transition-delay:200ms;">
                    <p class="text-4xl font-black text-white">15<span class="text-gold">+</span></p>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-muted">Servicios Premium</p>
                </div>
                <div class="reveal text-center" style="transition-delay:300ms;">
                    <p class="text-4xl font-black text-white">4.9</p>
                    <p class="mt-2 text-[10px] font-black uppercase tracking-widest text-muted">Calificación Promedio</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- SERVICIOS                                  -->
    <!-- ══════════════════════════════════════════ -->
    <section id="servicios" class="py-32 bg-[#0a0a0a] relative overflow-hidden">
        <div class="mx-auto max-w-7xl px-4">
            <div class="mb-20 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-4 flex items-center justify-center gap-3">
                    <span class="h-px w-8 bg-gold inline-block"></span>
                    Nuestros Servicios
                    <span class="h-px w-8 bg-gold inline-block"></span>
                </p>
                <h2 class="text-4xl font-black tracking-tight uppercase">Catálogo <span class="text-gold">Signature</span></h2>
                <p class="mt-4 text-muted text-sm leading-relaxed max-w-md mx-auto">Maestría artesanal aplicada a cada uno de nuestros tratamientos exclusivos.</p>
                <div class="mt-6 h-0.5 w-16 bg-gradient-to-r from-gold to-gold-dim mx-auto rounded-full shadow-[0_0_12px_rgba(212,175,55,0.5)] line-grow"></div>
            </div>

            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                @forelse($services as $i => $service)
                    <article class="reveal ui-card-premium p-10 group hover:border-gold/40" style="transition-delay: {{ $i * 100 }}ms;">
                        <div class="mb-8 flex items-start justify-between">
                            <div class="h-14 w-14 rounded-2xl bg-gold/5 border border-gold/10 text-gold flex items-center justify-center group-hover:scale-110 group-hover:bg-gold group-hover:text-black transition-all duration-500">
                                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7.848 8.25l1.536.887M7.848 8.25a3 3 0 11-5.196-3 3 3 0 015.196 3zm1.536.887a2.165 2.165 0 011.083 1.839c.005.351.054.695.196 1.024M9.384 9.137l2.077 1.199M13.5 15.75l1.83 1.83a3 3 0 006.086-1.803L21 15.75m-6.5-4.5l-3.83-2.212M7.848 15.75l1.536-.887M7.848 15.75a3 3 0 11-5.196 3 3 3 0 015.196-3zm1.536-.887a2.165 2.165 0 001.083-1.839 4.166 4.166 0 01.196-1.024M9.384 14.863l7.632-4.406M18.75 4.5l-2.928 1.69" />
                                </svg>
                            </div>
                            <span class="text-[10px] font-black text-white/10 group-hover:text-gold/20 transition-colors">
                                {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}
                            </span>
                        </div>
                        <h3 class="text-2xl font-black text-white uppercase">{{ $service->nombre }}</h3>
                        <x-service-desc :text="$service->descripcion ?: 'Una experiencia diseñada para resaltar tu mejor versión con técnica clásica.'"
                                        class="mt-4 text-sm text-muted font-medium leading-relaxed" />
                        <div class="mt-8 flex items-center justify-between">
                            <span class="text-2xl font-black text-white">${{ number_format($service->precio, 0) }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-gold bg-gold/5 px-3 py-1.5 rounded-full border border-gold/10">
                                {{ $service->duracion_min }} min
                            </span>
                        </div>
                        <!-- Barra deco hover -->
                        <div class="mt-6 h-px bg-white/5 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-gold/40 to-gold/5 rounded-full w-0 group-hover:w-full transition-all duration-700"></div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-3 text-center py-20 text-muted italic">Cargando catálogo premium...</div>
                @endforelse
            </div>

            <div class="mt-12 text-center">
                <a href="{{ route('services.public.index') }}" class="ui-btn-secondary px-10 py-4 border-white/10 hover:border-gold/40 text-[11px] tracking-[0.2em]">
                    Ver catálogo completo &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- CÓMO FUNCIONA                              -->
    <!-- ══════════════════════════════════════════ -->
    <section id="como-funciona" class="py-32 bg-[#0d0d0d] relative overflow-hidden">
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/20 to-transparent"></div>
        <div class="absolute bottom-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/10 to-transparent"></div>

        <div class="mx-auto max-w-7xl px-4">
            <div class="mb-20 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-4 flex items-center justify-center gap-3">
                    <span class="h-px w-8 bg-gold inline-block"></span>
                    Proceso
                    <span class="h-px w-8 bg-gold inline-block"></span>
                </p>
                <h2 class="text-4xl font-black tracking-tight uppercase">¿Cómo <span class="text-gold">Funciona</span>?</h2>
                <p class="mt-4 text-muted text-sm max-w-sm mx-auto leading-relaxed">Tres pasos simples para vivir la experiencia UrbanBlade.</p>
            </div>

            <div class="grid grid-cols-1 gap-10 md:grid-cols-3 relative">
                <!-- Línea conectora desktop -->
                <div class="hidden md:block absolute top-8 left-[calc(33.3%+1rem)] right-[calc(33.3%+1rem)] h-px bg-gradient-to-r from-gold/30 via-gold/50 to-gold/30"></div>

                @foreach([
                    ['num'=>'1','title'=>'Regístrate','desc'=>'Crea tu cuenta gratuita en menos de un minuto. Solo tu nombre y correo.','active'=>false],
                    ['num'=>'2','title'=>'Elige tu Cita','desc'=>'Selecciona el servicio, tu barbero favorito y el horario que prefieras.','active'=>true],
                    ['num'=>'3','title'=>'¡Luce Increíble!','desc'=>'Llega, relájate y déjate transformar. Sin esperas, sin sorpresas.','active'=>false],
                ] as $k => $step)
                    <div class="reveal text-center group" style="transition-delay: {{ $k * 150 }}ms;">
                        @if($step['active'])
                            <div class="relative inline-flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-gold to-gold-dim shadow-[0_0_30px_rgba(212,175,55,0.35)] mb-8 mx-auto group-hover:shadow-[0_0_50px_rgba(212,175,55,0.5)] transition-all duration-500">
                                <span class="text-2xl font-black text-black">{{ $step['num'] }}</span>
                            </div>
                        @else
                            <div class="relative inline-flex h-16 w-16 items-center justify-center rounded-full bg-[#111] border-2 border-gold/30 mb-8 mx-auto group-hover:border-gold group-hover:shadow-[0_0_25px_rgba(212,175,55,0.25)] transition-all duration-500">
                                <span class="text-2xl font-black text-gold">{{ $step['num'] }}</span>
                                <span class="absolute inset-0 rounded-full border border-gold/15 scale-110 group-hover:scale-125 transition-transform duration-500 opacity-0 group-hover:opacity-100"></span>
                            </div>
                        @endif
                        <h3 class="text-lg font-black text-white uppercase tracking-tight mb-3">{{ $step['title'] }}</h3>
                        <p class="text-sm text-muted leading-relaxed max-w-xs mx-auto">{{ $step['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-16 flex justify-center">
                <a href="{{ route('register') }}" class="ui-btn w-full sm:w-auto px-12 py-5 text-[13px] tracking-[0.15em] shadow-[0_0_50px_rgba(212,175,55,0.18)]">
                    Comenzar Ahora
                    <svg class="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- EQUIPO                                     -->
    <!-- ══════════════════════════════════════════ -->
    <section id="equipo" class="py-32 bg-[#0a0a0a]">
        <div class="mx-auto max-w-7xl px-4">
            <div class="mb-20 flex flex-col md:flex-row md:items-end justify-between gap-8">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-3 flex items-center gap-3">
                        <span class="h-px w-8 bg-gold inline-block"></span>
                        Nuestro Equipo
                    </p>
                    <h2 class="text-4xl font-black tracking-tight uppercase">Los <span class="text-gold">Maestros</span></h2>
                    <p class="mt-2 text-muted text-sm">Arquitectos de la imagen masculina</p>
                </div>
                <a href="{{ route('register') }}" class="text-[10px] font-black uppercase tracking-[0.2em] text-gold hover:text-white transition-colors flex items-center gap-2">
                    Ver todo el equipo <span>&rarr;</span>
                </a>
            </div>

            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @forelse($barbers as $k => $barber)
                    <a href="{{ $barber->slug ? route('barbers.public.show', $barber) : '#' }}"
                        class="barber-card group relative block reveal"
                       style="transition-delay: {{ $k * 120 }}ms;">
                        <div class="aspect-[3/4] overflow-hidden rounded-3xl border border-white/5 bg-[#111] relative">
                            @if($barber->foto)
                                <img src="{{ str_starts_with($barber->foto, 'http') ? $barber->foto : \Illuminate\Support\Facades\Storage::url($barber->foto) }}"
                                    class="h-full w-full object-cover grayscale group-hover:grayscale-0 transition-all duration-700 group-hover:scale-110"
                                    loading="lazy"
                                    alt="Foto de {{ $barber->user?->name }}">
                            @else
                                <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-gold/10 via-[#151515] to-[#0a0a0a]">
                                    <span class="text-4xl font-black text-gold/50 uppercase tracking-tighter">
                                        {{ collect(explode(' ', trim($barber->user?->name ?? '?')))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                    </span>
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>
                            <div class="absolute bottom-0 left-0 right-0 p-6 translate-y-1 group-hover:translate-y-0 transition-transform duration-400">
                                <h4 class="text-base font-black text-white uppercase">{{ $barber->user?->name }}</h4>
                                @php
                                    // Defensivo: 'especialidades' se valida como string en todos los
                                    // formularios (Api/Barber, Http/Requests/Barber), pero un registro
                                    // cargado fuera de esa validación (ej. seed/tinker manual) puede
                                    // guardarlo como array — evita un 500 en la landing pública si eso pasa.
                                    $especialidades = is_array($barber->especialidades)
                                        ? implode(', ', $barber->especialidades)
                                        : $barber->especialidades;
                                @endphp
                                <p class="text-[9px] font-bold text-gold uppercase tracking-widest mt-1">{{ $especialidades ?: 'Master Groomer' }}</p>
                                <div class="mt-3 flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-white/50 group-hover:text-gold/80 transition-colors duration-400">
                                    <span>Ver perfil</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                                </div>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-4 text-center py-20 text-muted">Nuestros maestros se están preparando...</div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- TESTIMONIALES                              -->
    <!-- ══════════════════════════════════════════ -->
    <section class="py-32 bg-[#0d0d0d] border-y border-white/5 relative overflow-hidden">
        <div class="absolute -top-40 right-0 h-96 w-96 rounded-full bg-gold/4 blur-[120px] pointer-events-none"></div>

        <div class="mx-auto max-w-7xl px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-center">

                <div class="reveal">
                    <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-4 flex items-center gap-3">
                        <span class="h-px w-8 bg-gold inline-block"></span>
                        Testimonios
                    </p>
                    <h2 class="text-4xl font-black tracking-tight uppercase leading-[1.1]">Lo que dicen <br>nuestros <span class="text-gradient-gold">Caballeros</span></h2>
                    <p class="mt-6 text-muted leading-relaxed max-w-sm text-sm">Nuestra reputación se ha forjado con precisión y satisfacción. Más de 500 clientes confían en UrbanBlade.</p>

                    <div class="mt-10 inline-flex items-center gap-5 border border-white/8 rounded-2xl px-6 py-4 bg-white/[0.02]">
                        <div class="text-center">
                            <p class="text-3xl font-black text-white">4.9</p>
                            <div class="flex justify-center gap-0.5 mt-1 text-gold" role="img" aria-label="Calificación 4.9 de 5 estrellas">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                        </div>
                        <div class="h-10 w-px bg-white/10"></div>
                        <div>
                            <div class="flex items-center gap-1 text-gold" role="img" aria-label="Calificación 5 de 5 estrellas">
                                @for ($i = 0; $i < 5; $i++)
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                @endfor
                            </div>
                            <p class="text-xs font-bold text-white mt-1.5">500+ Reseñas</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="reveal ui-card-premium p-8 border-gold/15 hover:border-gold/30 transition-colors" style="transition-delay:100ms;">
                        <div class="flex gap-0.5 mb-5">
                            @for($i=0;$i<5;$i++)<svg class="h-3.5 w-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                        </div>
                        <p class="text-base font-serif italic text-white leading-relaxed">"La atención al detalle es increíble. No es solo un corte, es un ritual de relajación. Totalmente recomendado."</p>
                        <div class="mt-5 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-gradient-to-br from-gold/35 to-gold/10 border border-gold/20 flex items-center justify-center text-[10px] font-black text-gold">R</div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gold">Ricardo Arjona &nbsp;·&nbsp; Cliente VIP</p>
                        </div>
                    </div>

                    <div class="reveal ui-card-premium p-8 border-white/5 hover:border-white/10 transition-colors" style="transition-delay:200ms;">
                        <div class="flex gap-0.5 mb-5">
                            @for($i=0;$i<5;$i++)<svg class="h-3.5 w-3.5 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>@endfor
                        </div>
                        <p class="text-base font-serif italic text-white leading-relaxed">"El sistema de reservas es súper rápido. Llego y mi barbero ya me espera. Eficiencia y lujo en un solo lugar."</p>
                        <div class="mt-5 flex items-center gap-3">
                            <div class="h-8 w-8 rounded-full bg-white/10 border border-white/10 flex items-center justify-center text-[10px] font-black text-white/60">J</div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-muted">Julian Casas &nbsp;·&nbsp; Emprendedor</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- CONTACTO                                   -->
    <!-- ══════════════════════════════════════════ -->
    <section id="contacto" class="py-32 bg-[#0a0a0a] relative overflow-hidden">
        <div class="mx-auto max-w-7xl px-4">
            <div class="mb-16 text-center">
                <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-4 flex items-center justify-center gap-3">
                    <span class="h-px w-8 bg-gold inline-block"></span>
                    Encuéntranos
                    <span class="h-px w-8 bg-gold inline-block"></span>
                </p>
                <h2 class="text-4xl font-black tracking-tight uppercase">Visítanos <span class="text-gold">Hoy</span></h2>
            </div>

            <div class="overflow-hidden rounded-3xl border border-white/8 shadow-[0_30px_80px_rgba(0,0,0,0.6)] reveal">
                <div class="flex flex-col lg:flex-row">
                    <div class="p-10 lg:p-14 lg:w-[38%] bg-[#0f0f0f] relative overflow-hidden">
                        <div class="absolute -bottom-20 -left-20 h-60 w-60 rounded-full bg-gold/5 blur-[80px] pointer-events-none"></div>
                        <h3 class="text-lg font-black text-white uppercase tracking-tighter mb-10 relative z-10">Información de Contacto</h3>
                        <div class="space-y-7 relative z-10">
                            @foreach([
                                ['icon'=>'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z','label'=>'Ubicación','text'=>'Av. de la Reforma 123,<br>Suite 405, CDMX'],
                                ['icon'=>'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z','label'=>'Contacto','text'=>'+52 55 1234 5678<br>hola@urbanblade.com'],
                                ['icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','label'=>'Horario','text'=>'Lun – Sáb: 9:00 – 21:00<br><span class="text-muted">Dom: Cerrado</span>'],
                            ] as $info)
                                <div class="flex gap-4">
                                    <div class="h-11 w-11 rounded-2xl bg-gold/8 text-gold flex items-center justify-center shrink-0 border border-gold/10">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="{{ $info['icon'] }}" stroke-width="2"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black text-gold uppercase tracking-widest mb-1">{{ $info['label'] }}</p>
                                        <p class="text-sm text-white font-medium leading-relaxed">{!! $info['text'] !!}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-10 pt-8 border-t border-white/5 relative z-10">
                            <p class="text-[9px] font-black uppercase tracking-widest text-muted mb-4">Síguenos</p>
                            <div class="flex gap-3">
                                @foreach([
                                    'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z',
                                    'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
                                    'M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.12 8.12 0 004.77 1.52V6.77a4.85 4.85 0 01-1-.08z'
                                ] as $svgPath)
                                    <a href="#" class="h-9 w-9 rounded-xl bg-white/5 border border-white/8 flex items-center justify-center text-muted hover:text-gold hover:bg-gold/10 hover:border-gold/20 transition-all">
                                        <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="{{ $svgPath }}"/></svg>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Mapa real (Google Maps embed, sin API key: query publica) -->
                    <div class="lg:flex-1 h-[320px] lg:h-auto bg-[#080808] relative overflow-hidden group">
                        <iframe
                            src="https://www.google.com/maps?q={{ urlencode('Av. de la Reforma 123, Suite 405, Ciudad de México') }}&output=embed"
                            class="absolute inset-0 h-full w-full border-0 grayscale-[35%] contrast-125 opacity-90 group-hover:opacity-100 group-hover:grayscale-0 transition-all duration-500"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            title="Ubicación de UrbanBlade en el mapa"></iframe>
                        <div class="pointer-events-none absolute inset-0 ring-1 ring-inset ring-white/10"></div>
                        <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode('Av. de la Reforma 123, Suite 405, Ciudad de México') }}"
                           target="_blank" rel="noopener"
                           class="absolute bottom-4 right-4 flex items-center gap-2 rounded-xl bg-[#0a0a0a]/90 border border-gold/20 px-4 py-2.5 text-[9px] font-black uppercase tracking-widest text-gold hover:bg-gold hover:text-black transition-all backdrop-blur">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Abrir en Google Maps
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- CTA FINAL                                  -->
    <!-- ══════════════════════════════════════════ -->
    <section class="py-32 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-[#0a0a0a] via-[#0d0d0d] to-[#0a0a0a]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_80%_60%_at_50%_50%,rgba(212,175,55,0.06),transparent)]"></div>
        <div class="absolute top-0 inset-x-0 h-px bg-gradient-to-r from-transparent via-gold/20 to-transparent"></div>

        <div class="relative z-10 mx-auto max-w-3xl px-4 text-center reveal">
            <span class="ui-badge mb-8 inline-flex border-gold/30 bg-gold/8 text-gold px-5 py-2">
                Reservas Abiertas
            </span>
            <h2 class="font-black text-white uppercase tracking-tighter leading-none mb-6"
                style="font-size: clamp(2.5rem, 7vw, 4.5rem);">
                ¿Listo para tu <br>
                <span class="text-gradient-gold italic font-serif lowercase normal-case" style="font-size: clamp(3rem, 8vw, 5.5rem);">mejor versión?</span>
            </h2>
            <p class="text-muted text-base mb-12 leading-relaxed">Únete a cientos de caballeros que ya confían en UrbanBlade para lucir siempre impecables.</p>
            <a href="{{ route('register') }}" class="ui-btn px-16 py-6 text-[13px] uppercase tracking-[0.25em] gold-glow-hover animate-float shadow-[0_0_60px_rgba(212,175,55,0.18)]">
                Reserva tu Turno Ahora
            </a>
        </div>
    </section>

    <!-- ══════════════════════════════════════════ -->
    <!-- FOOTER                                     -->
    <!-- ══════════════════════════════════════════ -->
    <footer class="border-t border-white/5 bg-[#040404] py-16 relative overflow-hidden">
        <div class="absolute -bottom-32 -left-32 h-80 w-80 rounded-full bg-gold/4 blur-3xl pointer-events-none"></div>

        <div class="mx-auto max-w-7xl px-4">
            <div class="flex flex-col md:flex-row justify-between items-start gap-12">
                <div class="max-w-xs">
                    <div class="flex items-center gap-3 mb-5">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-9 w-9 object-contain">
                        <span class="text-lg font-black uppercase tracking-tighter text-white">Urban<span class="text-gold">Blade</span></span>
                    </div>
                    <p class="text-xs text-muted leading-relaxed">
                        Líderes en el arte del grooming masculino. Unimos tradición y tecnología para una experiencia inigualable.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 gap-12">
                    <div>
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-white mb-5">Navegación</h5>
                        <ul class="space-y-3 text-[11px] font-bold text-muted uppercase tracking-wider">
                            <li><a href="#servicios"     class="hover:text-gold transition">Servicios</a></li>
                            <li><a href="#como-funciona" class="hover:text-gold transition">Proceso</a></li>
                            <li><a href="#equipo"        class="hover:text-gold transition">Maestros</a></li>
                            <li><a href="{{ route('login') }}" class="hover:text-gold transition">Acceso Staff</a></li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-white mb-5">Contacto</h5>
                        <ul class="space-y-3 text-[11px] font-bold text-muted tracking-wider">
                            <li>+52 55 1234 5678</li>
                            <li>hola@urbanblade.com</li>
                            <li class="text-muted/80">Lun – Sáb: 9 – 21h</li>
                        </ul>
                    </div>
                    <div>
                        <h5 class="text-[10px] font-black uppercase tracking-widest text-white mb-5">Social</h5>
                        <div class="flex gap-3">
                            <a href="#" class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:bg-gold/10 transition-all">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                            <a href="#" class="h-8 w-8 rounded-lg bg-white/5 flex items-center justify-center text-muted hover:text-gold hover:bg-gold/10 transition-all">
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-14 pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-[9px] font-black uppercase tracking-[0.3em] text-white/50">
                    &copy; {{ date('Y') }} UrbanBlade. Todos los derechos reservados.
                </p>
                <div class="flex gap-6 text-[9px] font-black uppercase tracking-[0.2em] text-white/50">
                    <a href="#" class="hover:text-white transition">Privacidad</a>
                    <a href="#" class="hover:text-white transition">Términos</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Chatbot -->
    <x-chatbot />

</div>

<script>
    // Parallax hero
    const heroBg = document.querySelector('.hero-bg');
    if (heroBg) {
        window.addEventListener('scroll', () => {
            heroBg.style.transform = `translateY(${window.scrollY * 0.22}px)`;
        }, { passive: true });
    }

    // Reveal on scroll usando IntersectionObserver (sin dependencias)
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
</script>
</body>
</html>
