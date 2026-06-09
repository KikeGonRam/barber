<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name'))</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div x-data="{ open: false }" class="ui-shell lg:grid lg:grid-cols-[280px_1fr]">
        @include('layouts.navigation')
        <div x-show="open" x-transition.opacity class="ui-mobile-drawer-backdrop" @click="open = false"></div>

        <div class="min-w-0">
            @isset($header)
                <header class="p-4 pb-3 sm:p-6 sm:pb-4">
                    <div class="ui-card-premium px-5 py-4 sm:px-6 sm:py-5">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="px-4 pb-8 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-[1340px]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    <x-toast />
    <x-command-palette />
    <div class="fixed bottom-6 right-6 z-50">
        <x-chatbot />
    </div>

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        // Confetti Listener
        window.addEventListener('celebrate', () => {
            confetti({
                particleCount: 150,
                spread: 100,
                origin: { y: 0.6 },
                colors: ['#d4af37', '#ffffff', '#aa8c2c']
            });
        });

        // Auto-notify from Laravel Session
        document.addEventListener('DOMContentLoaded', () => {
            @if(session('status'))
                window.dispatchEvent(new CustomEvent('notify', { 
                    detail: { message: "{{ session('status') }}", type: 'success' } 
                }));
                @if(str_contains(strtolower(session('status')), 'registrado') || str_contains(strtolower(session('status')), 'correcto'))
                    window.dispatchEvent(new CustomEvent('celebrate'));
                @endif
            @endif

            @if($errors->any())
                window.dispatchEvent(new CustomEvent('notify', { 
                    detail: { message: "{{ $errors->first() }}", type: 'error' } 
                }));
            @endif
        });
    </script>
</body>
</html>
