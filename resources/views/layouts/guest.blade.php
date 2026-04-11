<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'BarberPro') }} - Acceso Premium</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-ink bg-bg-main">
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-12">
        <!-- Background decorative elements -->
        <div class="absolute inset-0 z-0 opacity-10">
            <div class="absolute top-0 left-0 h-full w-full bg-[radial-gradient(circle_at_50%_50%,rgba(212,175,55,0.15),transparent_70%)]"></div>
        </div>

        <div class="relative z-10 w-full max-w-[440px]">
            <!-- Brand Section -->
            <div class="mb-10 flex flex-col items-center">
                <a href="/" class="group transition-transform hover:scale-105">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-gold shadow-[0_0_30px_rgba(212,175,55,0.3)] group-hover:shadow-[0_0_40px_rgba(212,175,55,0.5)] transition-all">
                        <svg class="h-10 w-10 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </a>
                <h1 class="mt-6 text-2xl font-black uppercase tracking-tighter text-white">Barber<span class="text-gold">Pro</span></h1>
                <p class="text-[10px] font-bold uppercase tracking-[0.3em] text-muted">Acceso Exclusivo</p>
            </div>

            <!-- Content Card -->
            <div class="ui-card-premium overflow-hidden border-white/5 bg-black/40 backdrop-blur-xl px-8 py-10 shadow-2xl ring-1 ring-white/10">
                {{ $slot }}
            </div>

            <!-- Footer Links -->
            <div class="mt-8 text-center">
                <p class="text-[10px] font-bold uppercase tracking-widest text-muted">
                    &copy; {{ date('Y') }} BarberPro Elite • Todos los derechos reservados
                </p>
            </div>
        </div>
    </div>
</body>
</html>
