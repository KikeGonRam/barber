<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Estilo no encontrado | UrbanBlade</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-ink bg-card">
    <div class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Background decorative elements -->
        <div class="absolute inset-0 z-0 opacity-10">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[600px] bg-[radial-gradient(circle,rgba(212,175,55,0.2)_0%,transparent_70%)]"></div>
        </div>

        <div class="relative z-10 px-4 text-center">
            <h1 class="select-none text-[clamp(6rem,24vw,12rem)] font-black leading-none text-ink/5">404</h1>
            <div class="-mt-12 rounded-[1.5rem] border border-ink/[0.08] bg-ink/[0.025] p-6 backdrop-blur-xl sm:-mt-20 sm:p-8">
                <span class="ui-badge border-gold/40 bg-gold/10 text-gold mb-6">Error de Navegación</span>
                <h2 class="mb-4 text-3xl font-black uppercase tracking-tighter text-ink sm:text-4xl">Página <span class="text-gold">no encontrada</span></h2>
                <p class="text-muted mx-auto mb-8 max-w-md text-sm font-medium leading-relaxed">La página que buscas no existe, fue movida o ya no está disponible. Puedes volver al inicio y continuar desde ahí.</p>

                <a href="/" class="ui-btn px-12 py-4 text-xs shadow-gold/20 animate-float">
                    Volver a UrbanBlade
                </a>
            </div>
        </div>
    </div>
</body>
</html>
