<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0a0a0a">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
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
    $_u        = auth()->user();
    $_unread   = $_u?->unreadNotifications()->count() ?? 0;
    $_navPrimary = \App\Helpers\NavigationMenu::primary($_u);
@endphp
@endauth

{{-- Single Alpine scope wraps everything so the sidebar open/rail/accordion state is shared --}}
<div x-data="{
        open: false,
        railCollapsed: localStorage.getItem('sidebarRail') === 'true',
        mode: 'desktop',
        openSections: {},
        initShell() {
            const mob = window.matchMedia('(max-width: 767px)');
            const tab = window.matchMedia('(min-width: 768px) and (max-width: 1023px)');
            const apply = () => { this.mode = mob.matches ? 'mobile' : (tab.matches ? 'tablet' : 'desktop'); };
            apply();
            mob.addEventListener('change', apply);
            tab.addEventListener('change', apply);
        },
        get railView() { return this.mode === 'tablet' || (this.mode === 'desktop' && this.railCollapsed); },
        toggleRail() { this.railCollapsed = !this.railCollapsed; localStorage.setItem('sidebarRail', this.railCollapsed); },
        initSections(navEl) {
            navEl.querySelectorAll('[data-sec-key]').forEach((el) => {
                const key = el.dataset.secKey;
                const active = el.dataset.secActive === '1';
                const stored = localStorage.getItem('nav_sec_' + key);
                this.openSections[key] = active ? true : (stored === null ? false : stored === 'true');
            });
        },
        toggleSection(key) {
            this.openSections[key] = !this.openSections[key];
            localStorage.setItem('nav_sec_' + key, this.openSections[key]);
        },
     }"
     x-init="initShell()"
     :style="'--sidebar-w: ' + (railCollapsed ? '88px' : '264px')">

    {{-- ── Mobile Top Bar (solo <768px; en tablet/desktop el sidebar va acoplado) ── --}}
    @auth
    <header class="mob-topbar md:hidden">
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
                {{ strtoupper(mb_substr($_u?->name ?? 'U', 0, 2)) }}
            </a>
        </div>
    </header>
    @endauth

    {{-- ── App Shell (sidebar + content) ─────────────────────────── --}}
    <div class="ui-shell pt-14 md:pt-0">
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

    {{-- ── Mobile Bottom Navigation (derivado de NavigationMenu::primary(), <768px) ── --}}
    @auth
    <nav class="mob-bottom-nav md:hidden" aria-label="Navegación principal">
        @foreach($_navPrimary as $navItem)
            <a href="{{ $navItem['href'] }}" class="mob-nav-item {{ $navItem['active'] ? 'is-active' : '' }}">
                <span class="mob-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $navItem['icon'] !!}</svg>
                    @if($navItem['badge'])
                        <span class="mob-nav-badge">{{ $navItem['badge'] > 9 ? '9+' : $navItem['badge'] }}</span>
                    @endif
                </span>
                <span class="mob-nav-label">{{ $navItem['label'] }}</span>
            </a>
        @endforeach

        {{-- "Más" abre el drawer completo: garantiza acceso a todo el menú en todos los roles --}}
        <button @click="open = !open" :class="open ? 'is-active' : ''"
                class="mob-nav-item" type="button" aria-label="Más opciones">
            <span class="mob-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/>
                </svg>
            </span>
            <span class="mob-nav-label">Más</span>
        </button>
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
