<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | Pagina no encontrada</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-4">
        <section class="ui-card w-full p-8 text-center sm:p-10">
            <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full border border-[#bfbfbf] bg-[#ececec]">
                <svg class="h-6 w-6 text-[#404040]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <circle cx="12" cy="12" r="9" />
                    <path d="M9.5 9.5h.01M14.5 9.5h.01M9 15c.8-.7 1.7-1 3-1s2.2.3 3 1" />
                </svg>
            </div>
            <h1 class="text-3xl font-semibold text-[#0d0d0d]">404</h1>
            <p class="mt-2 text-sm text-[#666]">La pagina que buscas no existe o fue movida.</p>
            <div class="mt-6">
                <a href="{{ url('/dashboard') }}" class="ui-btn">Volver al dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
