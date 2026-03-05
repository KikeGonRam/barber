<!doctype html>
<html lang="es-MX">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 | Error interno</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <main class="mx-auto flex min-h-screen w-full max-w-2xl items-center px-4">
        <section class="ui-card w-full p-8 text-center sm:p-10">
            <div class="mx-auto mb-5 flex h-12 w-12 items-center justify-center rounded-full border border-[#bfbfbf] bg-[#ececec]">
                <svg class="h-6 w-6 text-[#404040]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <path d="M10.2 3.6 2.7 17a2 2 0 0 0 1.75 3h15.1a2 2 0 0 0 1.75-3L13.8 3.6a2 2 0 0 0-3.6 0Z"/>
                    <path d="M12 9v4M12 17h.01"/>
                </svg>
            </div>
            <h1 class="text-3xl font-semibold text-[#0d0d0d]">500</h1>
            <p class="mt-2 text-sm text-[#666]">Ocurrio un error inesperado. Intenta nuevamente en unos minutos.</p>
            <div class="mt-6">
                <a href="{{ url('/dashboard') }}" class="ui-btn">Volver al dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
