<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0a">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    {{-- Anti-flash: aplica el tema ANTES de que el CSS pinte la pantalla --}}
    <script>
        (function () {
            var saved = localStorage.getItem('darkMode');
            var isDark = saved === null ? true : saved === 'true'; // default oscuro
            if (isDark) document.documentElement.classList.add('dark');
        })();
    </script>
    @safeVite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">

@auth
@php
    $_u      = auth()->user();
    $_admin  = $_u?->hasRole('administrador');
    $_recep  = $_u?->hasRole('recepcionista');
    $_barb   = $_u?->hasRole('barbero');
    $_cli    = $_u?->hasRole('cliente');
    $_unread = $_u?->unreadNotifications()->count() ?? 0;
@endphp
@endauth

{{-- Single Alpine scope wraps everything so the sidebar open state is shared --}}
<div x-data="{ open: false }">

    {{-- ── Mobile Top Bar ─────────────────────────────────────────── --}}
    @auth
    <header class="mob-topbar lg:hidden">
        <button @click="open = !open" :aria-expanded="open.toString()"
                class="mob-topbar-btn" aria-label="Abrir menú">
            <svg x-show="!open" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
            </svg>
            <svg x-show="open" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/>
            </svg>
        </button>

        <a href="{{ route('dashboard') }}" class="mob-topbar-brand">
            Urban<span class="text-gold">Blade</span>
        </a>

        <div class="flex items-center gap-2">
            <a href="{{ route('notifications.index') }}" class="mob-topbar-icon-btn relative" aria-label="Notificaciones">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span x-data
                      x-show="($store.notif?.unread ?? {{ $_unread }}) > 0"
                      x-text="($store.notif?.unread ?? {{ $_unread }}) > 9 ? '9+' : ($store.notif?.unread ?? {{ $_unread }})"
                      class="mob-notif-badge"
                      style="{{ $_unread > 0 ? '' : 'display:none' }}">{{ min($_unread, 9) }}</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="mob-topbar-avatar" aria-label="Mi perfil">
                {{ strtoupper(substr($_u?->name ?? 'U', 0, 2)) }}
            </a>
        </div>
    </header>
    @endauth

    {{-- ── App Shell (sidebar + content) ─────────────────────────── --}}
    <div class="ui-shell lg:grid lg:grid-cols-[280px_1fr] pt-14 lg:pt-0">
        @include('layouts.navigation')
        <div x-show="open" x-transition.opacity
             class="ui-mobile-drawer-backdrop" @click="open = false"></div>

        <div class="min-w-0">
            @isset($header)
                <header class="p-4 pb-3 sm:p-6 sm:pb-4">
                    <div class="ui-card-premium px-5 py-4 sm:px-6 sm:py-5">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="px-4 pb-28 sm:px-6 lg:pb-8 lg:px-8">
                <div class="mx-auto w-full max-w-[1340px] page-content">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>

    {{-- ── Mobile Bottom Navigation ──────────────────────────────── --}}
    @auth
    <nav class="mob-bottom-nav lg:hidden" aria-label="Navegación principal">

        @if($_admin || $_recep)
            <a href="{{ route('dashboard') }}"
               class="mob-nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Inicio</span>
            </a>
            <a href="{{ route('appointments.index') }}"
               class="mob-nav-item {{ request()->routeIs('appointments.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Citas</span>
            </a>
            <a href="{{ route('clients.index') }}"
               class="mob-nav-item {{ request()->routeIs('clients.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Clientes</span>
            </a>
            <a href="{{ route('payments.index') }}"
               class="mob-nav-item {{ request()->routeIs('payments.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><path d="M1 10h22"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Pagos</span>
            </a>
            <button @click="open = !open" :class="open ? 'is-active' : ''"
                    class="mob-nav-item" type="button" aria-label="Más opciones">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Más</span>
            </button>

        @elseif($_barb)
            <a href="{{ route('dashboard') }}"
               class="mob-nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Inicio</span>
            </a>
            <a href="{{ route('barber.agenda') }}"
               class="mob-nav-item {{ request()->routeIs('barber.agenda') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Agenda</span>
            </a>
            <a href="{{ route('barber.portfolio.index') }}"
               class="mob-nav-item {{ request()->routeIs('barber.portfolio.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Portfolio</span>
            </a>
            <a href="{{ route('barber.schedule.edit') }}"
               class="mob-nav-item {{ request()->routeIs('barber.schedule.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Horario</span>
            </a>
            <button @click="open = !open" :class="open ? 'is-active' : ''"
                    class="mob-nav-item" type="button" aria-label="Más opciones">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Más</span>
            </button>

        @elseif($_cli)
            <a href="{{ route('dashboard') }}"
               class="mob-nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Inicio</span>
            </a>
            <a href="{{ route('client.appointments.index') }}"
               class="mob-nav-item {{ request()->routeIs('client.appointments.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Mis Citas</span>
            </a>
            <a href="{{ route('client.barberos.index') }}"
               class="mob-nav-item {{ request()->routeIs('client.barberos.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121"/>
                        <circle cx="17" cy="7" r="3"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Barberos</span>
            </a>
            <a href="{{ route('social.feed') }}"
               class="mob-nav-item {{ request()->routeIs('social.feed') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Muro</span>
            </a>
            <a href="{{ route('client.facturas.index') }}"
               class="mob-nav-item {{ request()->routeIs('client.facturas.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Facturas</span>
            </a>

        @else
            <a href="{{ route('dashboard') }}"
               class="mob-nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Inicio</span>
            </a>
            <a href="{{ route('notifications.index') }}"
               class="mob-nav-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Alertas</span>
            </a>
            <a href="{{ route('profile.edit') }}"
               class="mob-nav-item {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <span class="mob-nav-label">Perfil</span>
            </a>
        @endif

    </nav>
    @endauth

</div>{{-- /x-data --}}

<x-toast />
<x-command-palette />
{{-- Chatbot floats above the bottom nav on mobile --}}
<div class="fixed z-50 bottom-20 right-4 lg:bottom-6 lg:right-6">
    <x-chatbot />
</div>
<x-notification-toaster />

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    window.addEventListener('celebrate', () => {
        confetti({ particleCount: 150, spread: 100, origin: { y: 0.6 }, colors: ['#d4af37', '#ffffff', '#aa8c2c'] });
    });
    document.addEventListener('DOMContentLoaded', () => {
        @if(session('status'))
            window.dispatchEvent(new CustomEvent('notify', { detail: { message: "{{ session('status') }}", type: 'success' } }));
            @if(str_contains(strtolower(session('status')), 'registrado') || str_contains(strtolower(session('status')), 'correcto'))
                window.dispatchEvent(new CustomEvent('celebrate'));
            @endif
        @endif
        @if($errors->any())
            window.dispatchEvent(new CustomEvent('notify', { detail: { message: "{{ $errors->first() }}", type: 'error' } }));
        @endif
    });
</script>
</body>
</html>
