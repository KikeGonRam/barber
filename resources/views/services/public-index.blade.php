<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menú de Servicios - BarberPro Elite</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
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
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-24">
                    <span class="ui-badge border-gold/40 bg-gold/10 text-gold mb-6">Carta de Experiencias</span>
                    <h1 class="text-5xl font-black text-white uppercase tracking-tighter mb-4">Servicios <span class="text-gold">Elite</span></h1>
                    <p class="text-muted max-w-2xl mx-auto font-medium">Cada servicio es un ritual de maestría diseñado para el caballero contemporáneo.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($services as $service)
                        <div class="ui-card-premium p-8 group flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-6">
                                    <span class="text-[9px] font-black uppercase tracking-widest text-gold/60 border border-gold/20 px-3 py-1 rounded-full">{{ $service->categoria }}</span>
                                    <span class="text-2xl font-black text-white">${{ number_format($service->precio, 2) }}</span>
                                </div>
                                <h3 class="text-2xl font-black text-white uppercase group-hover:text-gold transition-colors">{{ $service->nombre }}</h3>
                                <p class="mt-4 text-sm text-muted leading-relaxed">{{ $service->descripcion ?: 'Un servicio de alta precisión ejecutado por nuestros maestros barberos.' }}</p>
                            </div>
                            <div class="mt-10 pt-6 border-t border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-2 text-[10px] font-black text-muted uppercase tracking-widest">
                                    <svg class="h-4 w-4 text-gold/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2"/></svg>
                                    {{ $service->duracion_min }} Minutos
                                </div>
                                <a href="{{ route('register') }}" class="text-[9px] font-black text-gold uppercase tracking-widest group-hover:underline">Reservar &rarr;</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Call to Action -->
                <div class="mt-32 text-center">
                    <div class="ui-card-premium p-16 border-gold/30 bg-gradient-to-br from-gold/5 to-transparent relative overflow-hidden">
                        <div class="relative z-10">
                            <h2 class="text-4xl font-black text-white uppercase tracking-tighter mb-8">¿Deseas una experiencia personalizada?</h2>
                            <a href="{{ route('register') }}" class="ui-btn px-16 py-5 text-sm uppercase tracking-[0.3em]">Crea tu Cuenta Elite</a>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-white/5 py-12 text-center text-muted">
            <p class="text-[10px] font-black uppercase tracking-[0.3em]">BarberPro Elite Grooming &bull; {{ date('Y') }}</p>
        </footer>
    </div>
</body>
</html>
