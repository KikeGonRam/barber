<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ auth()->user()->theme ?? 'noir' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ (auth()->user()->theme ?? 'noir') === 'libreta' ? '#f3ede0' : '#0a0a0a' }}">
    <link rel="icon" type="image/png" href="{{ asset('images/logo-mark.png') }}">
    <title inertia>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

    @routes
    @safeVite(['resources/css/app.css', 'resources/js/inertia.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased">

{{--
    Root template de páginas Inertia+Vue. El "shell" (topbar móvil, sidebar,
    bottom-nav, widgets globales) es EXACTAMENTE el mismo Blade+Alpine que usa
    resources/views/layouts/app.blade.php para las páginas no migradas —
    misma partial (layouts.navigation), mismo helper NavigationMenu, mismos
    componentes x-toast/x-command-palette/x-chatbot/x-notification-toaster.
    Decisión deliberada (no un atajo): reescribir esa navegación en Vue ahora
    reimplementaría de cero el estado de rail colapsable, acordeón con
    localStorage, breakpoints responsive y el store de notificaciones en
    tiempo real — alto riesgo, cero beneficio en esta fase. Ver
    .claude/skills/inertia-vue-migration/SKILL.md (Fase 2).

    Solo el contenido de cada página (header-card + main) es Vue: eso lo
    provee AppLayout.vue (resources/js/Layouts/AppLayout.vue), montado por
    Inertia en el div de abajo.
--}}
@auth
@php
    $_u        = auth()->user();
    $_unread   = $_u?->unreadNotifications()->count() ?? 0;
    $_navPrimary = \App\Helpers\NavigationMenu::primary($_u);
@endphp
@endauth

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

    {{-- ── Mobile Top Bar ── --}}
    @auth
    <header class="mob-topbar md:hidden">
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

    {{-- ── App Shell (sidebar + contenido Inertia) ─────────────────────── --}}
    <div class="ui-shell pt-14 md:pt-0">
        @include('layouts.navigation')
        <div x-show="open" x-transition.opacity
             class="ui-mobile-drawer-backdrop" @click="open = false"></div>

        <div class="min-w-0">
            @inertia
        </div>
    </div>

    {{-- ── Mobile Bottom Navigation ── --}}
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
<x-chatbot />
<x-notification-toaster />

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<script>
    window.addEventListener('celebrate', () => {
        confetti({ particleCount: 150, spread: 100, origin: { y: 0.6 }, colors: ['#d4af37', '#ffffff', '#aa8c2c'] });
    });
</script>
</body>
</html>
