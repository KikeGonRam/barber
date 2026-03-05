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
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-md">
            <div class="ui-panel mb-3 flex items-center justify-center px-6 py-5">
                <a href="/" class="flex items-center gap-3">
                    <x-application-logo class="h-9 w-9 fill-current text-[#f2f2f2]" />
                    <span class="text-sm font-semibold tracking-wide text-[#f2f2f2]">Barbershop</span>
                </a>
            </div>
            <div class="ui-card overflow-hidden px-6 py-5 sm:px-7 sm:py-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
