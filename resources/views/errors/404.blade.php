<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Estilo no encontrado | BarberPro</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,900&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-white bg-[#0a0a0a]">
    <div class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Background decorative elements -->
        <div class="absolute inset-0 z-0 opacity-10">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[600px] w-[600px] bg-[radial-gradient(circle,rgba(212,175,55,0.2)_0%,transparent_70%)]"></div>
        </div>

        <div class="relative z-10 text-center px-4">
            <h1 class="text-[180px] font-black text-white/5 leading-none select-none">404</h1>
            <div class="-mt-20">
                <span class="ui-badge border-gold/40 bg-gold/10 text-gold mb-6">Error de Navegación</span>
                <h2 class="text-4xl font-black uppercase tracking-tighter mb-4">Parece que te has <span class="text-gold">pasado de corte</span></h2>
                <p class="text-muted max-w-md mx-auto mb-10 font-medium">La página que buscas no existe o ha sido movida a otra estación. Regresemos al inicio para un ajuste.</p>
                
                <a href="/" class="ui-btn px-12 py-4 text-xs shadow-gold/20 animate-float">
                    Volver a BarberPro Elite
                </a>
            </div>
        </div>
    </div>
</body>
</html>
