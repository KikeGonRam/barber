<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>500 - Error de Sistema | UrbanBlade</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-ink bg-card">
    <div class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Background decorative elements -->
        <div class="absolute inset-0 z-0 opacity-10">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[600px] bg-[radial-gradient(circle,rgba(239,68,68,0.2)_0%,transparent_70%)]"></div>
        </div>

        <div class="relative z-10 px-4 text-center">
            <h1 class="select-none text-[clamp(6rem,24vw,12rem)] font-black leading-none text-ink/5">500</h1>
            <div class="-mt-12 rounded-[1.5rem] border border-ink/[0.08] bg-ink/[0.025] p-6 backdrop-blur-xl sm:-mt-20 sm:p-8">
                <span class="ui-badge border-red-500/40 bg-red-500/10 text-red-400 mb-6 uppercase tracking-widest">Error de Servidor</span>
                <h2 class="mb-4 text-3xl font-black uppercase tracking-tighter text-ink sm:text-4xl">Algo salió <span class="text-red-500">mal</span></h2>
                <p class="text-muted mx-auto mb-8 max-w-md text-sm font-medium leading-relaxed">Estamos experimentando un fallo interno. Inténtalo de nuevo en unos minutos o avisa a administración si el problema continúa.</p>

                <a href="/" class="ui-btn px-12 py-4 text-xs shadow-white/5">
                    Volver al Inicio
                </a>
            </div>
        </div>
    </div>
</body>
</html>
