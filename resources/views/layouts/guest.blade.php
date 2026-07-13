<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">

    <title>{{ config('app.name') }} - Acceso Premium</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#050505] overflow-hidden lg:overflow-auto">

    <div class="min-h-screen flex flex-col lg:flex-row">

        <!-- ═══════════════════════════════════════════════ -->
        <!-- LEFT PANEL — Branding Showcase (oculto móvil)  -->
        <!-- ═══════════════════════════════════════════════ -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-[55%] relative flex-col justify-between overflow-hidden bg-[#050505]">

            <!-- Imagen de fondo barbería con overlay -->
            <div class="absolute inset-0 z-0">
                <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?q=80&w=2074&auto=format&fit=crop')] bg-cover bg-center opacity-20"></div>
                <div class="absolute inset-0 bg-gradient-to-br from-[#050505] via-[#050505]/70 to-[#0a0a0a]"></div>
                <!-- Grid pattern overlay -->
                <div class="absolute inset-0 opacity-[0.03]" style="background-image: linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px); background-size: 40px 40px;"></div>
            </div>

            <!-- Glow orbs decorativos -->
            <div class="absolute top-1/4 -left-32 h-96 w-96 rounded-full bg-gold/8 blur-[100px] pointer-events-none"></div>
            <div class="absolute bottom-1/3 right-0 h-64 w-64 rounded-full bg-gold/5 blur-[80px] pointer-events-none"></div>

            <!-- Contenido del panel -->
            <div class="relative z-10 flex flex-col h-full p-12 xl:p-16">

                <!-- Logo top -->
                <a href="/" class="flex items-center gap-3 group w-fit">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-11 w-11 object-contain">
                    <div>
                        <span class="block text-xl font-black uppercase tracking-tighter text-white">Urban<span class="text-gold">Blade</span></span>
                        <span class="block text-[9px] font-bold uppercase tracking-[0.3em] text-muted -mt-0.5">Elite Grooming Studio</span>
                    </div>
                </a>

                <!-- Tagline central -->
                <div class="flex-1 flex flex-col justify-center">
                    <div class="max-w-md">
                        <p class="text-[10px] font-black uppercase tracking-[0.4em] text-gold mb-6 flex items-center gap-3">
                            <span class="h-px w-8 bg-gold inline-block"></span>
                            Arte & Precisión
                        </p>
                        <h2 class="text-5xl xl:text-6xl font-black uppercase tracking-tighter text-white leading-[0.9] mb-8">
                            Donde el <br><span class="text-gradient-gold italic font-serif normal-case text-6xl xl:text-7xl">estilo</span><br> toma vida.
                        </h2>
                        <p class="text-base text-muted leading-relaxed max-w-sm">
                            Más de una década perfeccionando el arte del grooming masculino. Tu próximo gran look comienza aquí.
                        </p>
                    </div>

                    <!-- Stats en fila -->
                    <div class="mt-12 grid grid-cols-3 gap-6 max-w-md">
                        <div class="border-l-2 border-gold/30 pl-4">
                            <p class="text-2xl font-black text-white">500<span class="text-gold">+</span></p>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-muted mt-0.5">Clientes felices</p>
                        </div>
                        <div class="border-l-2 border-gold/30 pl-4">
                            <p class="text-2xl font-black text-white">10<span class="text-gold">+</span></p>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-muted mt-0.5">Años de experiencia</p>
                        </div>
                        <div class="border-l-2 border-gold/30 pl-4">
                            <p class="text-2xl font-black text-white">4.9</p>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-muted mt-0.5">Calificación</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial bottom -->
                <div class="border border-white/5 rounded-2xl p-6 bg-white/[0.02] backdrop-blur-sm">
                    <div class="flex items-start gap-4">
                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gold/40 to-gold/10 border border-gold/20 flex items-center justify-center shrink-0">
                            <span class="text-gold font-black text-sm">R</span>
                        </div>
                        <div>
                            <p class="text-sm text-white/80 leading-relaxed italic font-serif">"El mejor lugar para un corte premium. El sistema de reservas es brillante."</p>
                            <p class="text-[9px] font-black uppercase tracking-widest text-gold mt-2">— Ricardo M. · Cliente VIP</p>
                        </div>
                    </div>
                    <!-- Estrellas -->
                    <div class="flex gap-0.5 mt-3 ml-14">
                        @for ($i = 0; $i < 5; $i++)
                            <svg class="h-3 w-3 text-gold" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        @endfor
                    </div>
                </div>
            </div>

            <!-- Línea dorada vertical decorativa -->
            <div class="absolute right-0 inset-y-0 w-px bg-gradient-to-b from-transparent via-gold/20 to-transparent"></div>
        </div>

        <!-- ═══════════════════════════════════════════════ -->
        <!-- RIGHT PANEL — Formulario                       -->
        <!-- ═══════════════════════════════════════════════ -->
        <div class="flex-1 lg:w-1/2 xl:w-[45%] flex flex-col items-center justify-center relative min-h-screen lg:min-h-auto overflow-y-auto bg-[#080808]">

            <!-- Glow sutil de fondo -->
            <div class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gold/15 to-transparent lg:hidden"></div>
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-80 w-80 rounded-full bg-gold/4 blur-[100px]"></div>
            </div>

            <div class="relative z-10 w-full max-w-[420px] px-6 py-10 sm:px-8">

                <!-- Logo móvil (sólo visible en móvil) -->
                <div class="lg:hidden mb-10 flex flex-col items-center">
                    <a href="/" class="group transition-transform hover:scale-105">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-14 w-14 object-contain mx-auto">
                    </a>
                    <h1 class="mt-4 text-2xl font-black uppercase tracking-tighter text-white">Urban<span class="text-gold">Blade</span></h1>
                    <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-muted">Acceso Exclusivo</p>
                </div>

                <!-- Contenido del slot (form) dentro de una card -->
                <div class="guest-form-card relative rounded-2xl overflow-hidden border border-white/8 bg-[#0f0f0f]/90 backdrop-blur-xl px-8 py-10 shadow-[0_25px_60px_rgba(0,0,0,0.8)]">
                    <!-- Shimmer top edge -->
                    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-gold/30 to-transparent"></div>
                    {{ $slot }}
                </div>

                <!-- Footer -->
                <div class="mt-8 text-center">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-white/45">
                        &copy; {{ date('Y') }} UrbanBlade &nbsp;·&nbsp; Todos los derechos reservados
                    </p>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
