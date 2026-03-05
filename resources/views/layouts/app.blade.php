<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="ui-shell lg:grid lg:grid-cols-[280px_1fr]">
        @include('layouts.navigation')

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
</body>
</html>
