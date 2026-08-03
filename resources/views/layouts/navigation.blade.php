@php
    $user = auth()->user();
    $isAdmin = $user?->hasRole('administrador');
    $isBarber = $user?->hasRole('barbero');
    $isReception = $user?->hasRole('recepcionista');
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $navSections = \App\Helpers\NavigationMenu::sections($user);
@endphp

<nav :class="open ? 'translate-x-0 shadow-[12px_0_50px_rgba(0,0,0,0.7)]' : '-translate-x-full'"
    class="ui-panel ui-mobile-drawer ui-mobile-drawer-panel fixed inset-y-0 left-0 z-40 m-0 flex h-screen w-[min(88vw,320px)] flex-col p-3 bg-gradient-to-b from-[#1a1a1a] to-[#0a0a0a] border-[#2a2a2a] overflow-hidden transition-[transform,box-shadow] duration-[380ms] ease-[cubic-bezier(0.32,0.72,0,1)] md:sticky md:top-6 md:z-auto md:m-4 md:h-[calc(100vh-2rem)] md:w-auto md:translate-x-0 md:shadow-none md:transition-none">
    <!-- Brand Section (Fixed) -->
    <div class="flex items-center justify-between px-2 pb-8 pt-4 flex-shrink-0 md:flex-col md:justify-center md:gap-3"
        :class="!railCollapsed ? 'lg:flex-row lg:justify-between lg:gap-0' : ''">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-sm font-semibold tracking-wide text-white group">
            <img src="{{ asset('images/logo-mark.png') }}" alt="UrbanBlade" class="h-10 w-10 object-contain group-hover:scale-105 transition-transform duration-300 flex-shrink-0">
            <div class="hidden" :class="!railCollapsed ? 'lg:block' : ''">
                <span class="block text-lg font-black uppercase tracking-tighter leading-tight">Urban<span class="text-gold">Blade</span></span>
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-muted">Management System</span>
            </div>
        </a>
        <button @click="open = false" class="nav-close-btn md:hidden" aria-label="Cerrar menú">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/>
            </svg>
        </button>
        <button @click="toggleRail()" class="nav-rail-btn hidden lg:flex" :aria-label="railCollapsed ? 'Expandir menú' : 'Colapsar menú'" title="Colapsar / expandir">
            <svg class="h-3.5 w-3.5 transition-transform duration-300" :class="railCollapsed ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>

    <!-- Links Section (Scrollable) — acordeón inteligente en modo expandido, iconos agrupados en rail -->
    <div :class="open ? 'block' : 'hidden'" class="hidden flex-1 space-y-2 overflow-y-auto md:block pr-1 custom-scrollbar min-h-0"
         x-init="initSections($el)">
        @foreach($navSections as $section)
            @php $k = $section['key']; @endphp
            <div class="space-y-1"
                @if($section['collapsible']) data-sec-key="{{ $k }}" data-sec-active="{{ $section['active'] ? '1' : '0' }}" @endif>

                {{-- Divisor entre grupos (solo modo rail) --}}
                @unless($loop->first)
                    <div x-show="railView" x-cloak class="mx-auto my-2 h-px w-6 bg-white/10"></div>
                @endunless

                {{-- Encabezado de sección --}}
                @if($section['collapsible'])
                    <button type="button" @click="toggleSection('{{ $k }}')" x-show="!railView" x-cloak
                            class="w-full flex items-center gap-1.5 px-3 pt-1 pb-0.5 text-[10px] uppercase tracking-widest text-gold font-black opacity-80 hover:opacity-100 transition"
                            :aria-expanded="(openSections['{{ $k }}'] ?? false).toString()">
                        <svg class="h-3 w-3 flex-shrink-0 transition-transform duration-200" :class="openSections['{{ $k }}'] ? 'rotate-90' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span>{{ $section['title'] }}</span>
                        <span class="ml-auto text-[9px] text-muted font-bold" x-show="!openSections['{{ $k }}']" x-cloak>{{ $section['count'] }}</span>
                    </button>
                @else
                    <p x-show="!railView" x-cloak class="px-3 pb-0.5 text-[10px] uppercase tracking-widest text-gold font-black opacity-80">{{ $section['title'] }}</p>
                @endif

                {{-- Ítems --}}
                <div class="space-y-1" @if($section['collapsible']) x-show="railView || openSections['{{ $k }}']" x-cloak @endif>
                    @foreach($section['items'] as $item)
                        <x-nav-link :href="$item['href']" :active="$item['active']" title="{{ $item['label'] }}"
                            class="md:justify-center md:px-0" x-bind:class="!railCollapsed ? 'lg:justify-start lg:px-3' : ''">
                            <svg class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">{!! $item['icon'] !!}</svg>
                            <span class="md:hidden" :class="!railCollapsed ? 'lg:inline' : ''">{{ $item['label'] }}</span>
                            @if($item['badge'])
                                <span class="ml-auto h-5 min-w-[20px] px-1 rounded-full bg-gold text-black text-[10px] font-black flex items-center justify-center">{{ $item['badge'] }}</span>
                            @endif
                        </x-nav-link>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- User Footer Section (Fixed) -->
    <div :class="open ? 'block' : 'hidden'" class="hidden space-y-4 pt-4 md:block border-t border-white/10 flex-shrink-0">
        <!-- Dark Mode Toggle y Keyboard Shortcuts -->
        <div class="px-2 mb-2 flex items-center gap-2 justify-center md:justify-start" :class="!railCollapsed ? 'lg:justify-between' : ''">
            <x-dark-mode-toggle />
            <div class="hidden" :class="!railCollapsed ? 'lg:block' : ''">
                <x-keyboard-shortcuts-help />
            </div>
        </div>

        <!-- Shortcut Hint -->
        <div class="px-2 mb-2 hidden" :class="!railCollapsed ? 'lg:block' : ''">
            <div class="flex items-center justify-between rounded-lg bg-white/5 px-2 py-1.5 border border-white/5">
                <span class="text-[9px] font-black uppercase text-muted tracking-widest">Atajos</span>
                <div class="flex items-center gap-1">
                    <kbd class="px-1 py-0.5 rounded border border-white/10 bg-black text-[8px] font-bold text-gold">Ctrl</kbd>
                    <span class="text-[8px] text-muted">+</span>
                    <kbd class="px-1 py-0.5 rounded border border-white/10 bg-black text-[8px] font-bold text-gold">K</kbd>
                </div>
            </div>
        </div>

         <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" title="Notificaciones">
            <svg class="h-4 w-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="flex items-center gap-2 w-full justify-between md:hidden" :class="!railCollapsed ? 'lg:flex' : ''">
                Notificaciones
                {{-- Badge: SSR inline style ensures initial visibility; Alpine store drives real-time updates --}}
                <span
                    x-data
                    x-show="($store.notif?.unread ?? {{ $unread }}) > 0"
                    class="relative flex h-4 w-4"
                    style="{{ $unread > 0 ? '' : 'display:none' }}"
                >
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-gold/40 opacity-75"></span>
                    <span class="relative inline-flex items-center justify-center rounded-full bg-gold h-4 w-4 text-[10px] font-black text-black leading-none"
                          x-text="($store.notif?.unread ?? {{ $unread }}) > 99 ? '99+' : ($store.notif?.unread ?? {{ $unread }})">{{ min($unread, 99) }}</span>
                </span>
            </span>
            @if($unread > 0)
                <span class="hidden md:block ml-auto h-2 w-2 rounded-full bg-gold" :class="!railCollapsed ? 'lg:hidden' : ''"></span>
            @endif
        </x-nav-link>

        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-2 rounded-xl py-2 hover:bg-white/5 transition-colors group justify-center md:justify-start" :class="!railCollapsed ? 'lg:justify-start' : ''" title="{{ $user?->name }}">
            <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/20 flex items-center justify-center text-[11px] font-black text-gold shrink-0 group-hover:from-gold/50 group-hover:to-gold/20 transition-all">
                {{ strtoupper(mb_substr($user?->name ?? 'U', 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0 md:hidden" :class="!railCollapsed ? 'lg:block' : ''">
                <p class="truncate text-xs font-bold text-white">{{ $user?->name }}</p>
                <p class="truncate text-[9px] font-bold text-muted uppercase tracking-wider">
                    @if($isAdmin) Administrador
                    @elseif($isBarber) Barbero
                    @elseif($isReception) Recepcionista
                    @else Cliente
                    @endif
                </p>
            </div>
            <svg class="h-3.5 w-3.5 text-muted group-hover:text-gold transition-colors shrink-0 md:hidden" :class="!railCollapsed ? 'lg:block' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="px-2">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/20" title="Cerrar Sesión">
                <svg class="h-3 w-3 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="md:hidden" :class="!railCollapsed ? 'lg:inline' : ''">Cerrar Sesion</span>
            </button>
        </form>
    </div>
</nav>
