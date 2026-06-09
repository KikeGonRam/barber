<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actualización de Sistema | UrbanBlade</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-white bg-[#0a0a0a]">
    <div class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Tech Decorative Background -->
        <div class="absolute inset-0 z-0 opacity-20">
            <div class="absolute top-0 left-0 w-full h-full bg-[linear-gradient(rgba(212,175,55,0.05)_1px,transparent_1px),linear-gradient(90px,rgba(212,175,55,0.05)_1px,transparent_1px)] bg-[size:40px_40px]"></div>
        </div>

        <div class="relative z-10 text-center px-4 max-w-2xl">
            <!-- Professional Logo Display -->
            <div class="flex items-center justify-center gap-3 mb-12">
                <div class="h-12 w-12 rounded-2xl bg-gold flex items-center justify-center text-black shadow-lg shadow-gold/20 animate-pulse">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="text-2xl font-black uppercase tracking-tighter">Urban<span class="text-gold">Blade</span> <span class="text-[10px] bg-white/10 px-2 py-0.5 rounded ml-2 text-muted">v2.0</span></span>
            </div>

            <span class="ui-badge border-gold/40 bg-gold/10 text-gold mb-8 px-6 py-2">Deployment en Progreso</span>
            
            <h1 class="text-5xl sm:text-6xl font-black uppercase tracking-tighter mb-6 leading-none">Generando una <br> <span class="text-gradient-gold italic font-serif normal-case">nueva versión</span></h1>
            
            <p class="text-muted text-lg font-medium leading-relaxed mb-12">
                Estamos implementando mejoras críticas en nuestra suite de gestión para ofrecerte una experiencia más rápida, fluida y exclusiva. 
            </p>

            <!-- Deployment Progress Mockup -->
            <div class="ui-card-premium p-8 border-white/5 bg-black/40 backdrop-blur-md mb-12">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-[10px] font-black uppercase text-gold tracking-widest">Estado del Despliegue</span>
                    <span class="text-[10px] font-black text-white">94%</span>
                </div>
                <div class="w-full h-1.5 bg-white/5 rounded-full overflow-hidden border border-white/10">
                    <div class="h-full bg-gold shadow-[0_0_15px_rgba(212,175,55,0.5)] animate-shimmer" style="width: 94%"></div>
                </div>
                <div class="mt-6 grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-2 text-[9px] font-bold text-muted uppercase">
                        <span class="h-1 w-1 rounded-full bg-green-500"></span> BD Optimizada
                    </div>
                    <div class="flex items-center gap-2 text-[9px] font-bold text-muted uppercase">
                        <span class="h-1 w-1 rounded-full bg-green-500"></span> Assets Compilados
                    </div>
                    <div class="flex items-center gap-2 text-[9px] font-bold text-muted uppercase">
                        <span class="h-1 w-1 rounded-full bg-green-500"></span> UI Refactorizada
                    </div>
                    <div class="flex items-center gap-2 text-[9px] font-bold text-muted uppercase">
                        <span class="h-1 w-1 rounded-full bg-gold animate-pulse"></span> Finalizando...
                    </div>
                </div>
            </div>
            
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-[10px] font-black uppercase tracking-[0.3em] text-muted hover:text-white transition underline underline-offset-8">
                    Cerrar sesión y volver más tarde
                </button>
            </form>
        </div>
    </div>
</body>
</html>
