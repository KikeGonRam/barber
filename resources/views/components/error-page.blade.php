@props([
    'code',
    'eyebrow',
    'title',
    'accent',
    'message',
    'mascot',
    'mascotName',
    'action' => '/',
    'actionLabel' => 'Volver al inicio',
])
@php
    $allowedThemes = ['noir', 'acero', 'salon', 'libreta'];
    $savedTheme = auth()->user()?->theme ?? request()->cookie('ub_theme', 'noir');
    $theme = in_array($savedTheme, $allowedThemes, true) ? $savedTheme : 'noir';
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $theme === 'libreta' ? '#f3ede0' : '#0a0a0a' }}">
    <title>{{ $code }} - {{ $title }} | UrbanBlade</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/urbanblade-mark.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800&display=swap" rel="stylesheet" />
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <main class="ub-error-page" data-mascot-stage>
        <div class="ub-error-orbit ub-error-orbit-a" aria-hidden="true"></div>
        <div class="ub-error-orbit ub-error-orbit-b" aria-hidden="true"></div>

        <a href="/" class="ub-error-brand" aria-label="Ir al inicio de UrbanBlade">
            <x-brand-mark class="h-11 w-11" />
            <span>Urban<strong>Blade</strong></span>
        </a>

        <section class="ub-error-shell" aria-labelledby="error-title">
            <div class="ub-error-copy">
                <span class="ub-error-eyebrow">{{ $eyebrow }}</span>
                <p class="ub-error-code" aria-hidden="true">{{ $code }}</p>
                <h1 id="error-title">{{ $title }} <span>{{ $accent }}</span></h1>
                <p class="ub-error-message">{{ $message }}</p>
                <a href="{{ $action }}" class="ui-btn ub-error-action">{{ $actionLabel }}</a>
            </div>

            <figure class="ub-mascot" data-mascot>
                <span class="ub-mascot-halo" aria-hidden="true"></span>
                <img src="{{ asset('images/mascots/'.$mascot) }}" alt="{{ $mascotName }}, mascota de UrbanBlade" draggable="false">
                <figcaption>{{ $mascotName }} está aquí para ayudarte</figcaption>
            </figure>
        </section>
    </main>

    <script>
        (() => {
            const stage = document.querySelector('[data-mascot-stage]');
            const mascot = document.querySelector('[data-mascot]');
            if (!stage || !mascot || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            stage.addEventListener('pointermove', ({ clientX, clientY }) => {
                const x = (clientX / innerWidth - .5) * 12;
                const y = (clientY / innerHeight - .5) * 8;
                mascot.style.setProperty('--mascot-x', `${x}px`);
                mascot.style.setProperty('--mascot-y', `${y}px`);
            }, { passive: true });
        })();
    </script>
</body>
</html>
