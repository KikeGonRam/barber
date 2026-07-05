@php
    $user = auth()->user();
    $isAdmin = $user?->hasRole('administrador');
    $isReception = $user?->hasRole('recepcionista');
    $isBarber = $user?->hasRole('barbero');
    $isClient = $user?->hasRole('cliente');
    $unread = $user?->unreadNotifications()->count() ?? 0;
@endphp

<nav :class="open ? 'translate-x-0 shadow-[12px_0_50px_rgba(0,0,0,0.7)]' : '-translate-x-full'"
     class="ui-panel ui-mobile-drawer ui-mobile-drawer-panel fixed inset-y-0 left-0 z-40 m-0 flex h-screen w-[min(88vw,320px)] flex-col p-3 bg-gradient-to-b from-[#1a1a1a] to-[#0a0a0a] border-[#2a2a2a] overflow-hidden transition-[transform,box-shadow] duration-[380ms] ease-[cubic-bezier(0.32,0.72,0,1)] lg:sticky lg:top-6 lg:z-auto lg:m-6 lg:h-[calc(100vh-3rem)] lg:w-auto lg:translate-x-0 lg:shadow-none lg:transition-none">
    <!-- Brand Section (Fixed) -->
    <div class="flex items-center justify-between px-2 pb-8 pt-4 flex-shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-sm font-semibold tracking-wide text-white group">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-gold to-gold-dim shadow-lg shadow-gold/20 group-hover:shadow-gold/40 group-hover:scale-105 transition-all duration-300">
                <svg class="h-6 w-6 text-black" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div class="hidden lg:block">
                <span class="block text-lg font-black uppercase tracking-tighter leading-tight">Urban<span class="text-gold">Blade</span></span>
                <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-muted">Management System</span>
            </div>
        </a>
        <button @click="open = false" class="nav-close-btn lg:hidden" aria-label="Cerrar menú">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6 18L18 6M6 6l12 12" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <!-- Links Section (Scrollable) -->
    <div :class="open ? 'block' : 'hidden'" class="hidden flex-1 space-y-6 overflow-y-auto lg:block pr-1 custom-scrollbar min-h-0">
        
        <!-- Section: General -->
        <div class="space-y-1">
            <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Principal</p>
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>
                <span>Dashboard</span>
            </x-nav-link>
            <x-nav-link :href="route('social.feed')" :active="request()->routeIs('social.feed')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                <span>Muro Inspiración</span>
            </x-nav-link>

        </div>

        @if($isAdmin || $isReception)
            <!-- Section: Operación -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Operacion</p>
                <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.index') || request()->routeIs('appointments.create') || request()->routeIs('appointments.edit')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                    <span>Citas</span>
                </x-nav-link>
                <x-nav-link :href="route('appointments.calendar')" :active="request()->routeIs('appointments.calendar*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/><circle cx="12" cy="15" r="2"/></svg>
                    <span>Calendario</span>
                </x-nav-link>

                <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Clientes</span>
                </x-nav-link>

                <x-nav-link :href="route('payments.index')" :active="request()->routeIs('payments.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Pagos</span>
                </x-nav-link>
                <x-nav-link :href="route('inventory.movements.index')" :active="request()->routeIs('inventory.movements.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/></svg>
                    <span>Movimientos</span>
                </x-nav-link>
            </div>
        @endif

        @if($isAdmin)
            <!-- Section: Gestion -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Gestion</p>
                <x-nav-link :href="route('barbers.index')" :active="request()->routeIs('barbers.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Barberos</span>
                </x-nav-link>

                <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    <span>Usuarios</span>
                </x-nav-link>

                <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    <span>Servicios</span>
                </x-nav-link>

                <x-nav-link :href="route('inventory.products.index')" :active="request()->routeIs('inventory.products.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                    <span>Productos</span>
                </x-nav-link>

                <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"/></svg>
                    <span>Configuracion</span>
                </x-nav-link>
            </div>
            <!-- Section: Analisis -->
            <div class="space-y-1">
                <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Analisis</p>
                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span>Reportes</span>
                </x-nav-link>
                <x-nav-link :href="route('logs.index')" :active="request()->routeIs('logs.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>
                    <span>Logs</span>
                </x-nav-link>
            </div>
        @endif

        @if($isBarber)
            <div class="space-y-1">
                <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Mi Espacio</p>
                <x-nav-link :href="route('barber.agenda')" :active="request()->routeIs('barber.agenda')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <span>Mi Agenda</span>
                </x-nav-link>
                <x-nav-link :href="route('barber.portfolio.index')" :active="request()->routeIs('barber.portfolio.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>Mi Portafolio</span>
                </x-nav-link>
                <x-nav-link :href="route('barber.schedule.edit')" :active="request()->routeIs('barber.schedule.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <span>Mi Horario</span>
                </x-nav-link>
                <x-nav-link :href="route('barber.profile.edit')" :active="request()->routeIs('barber.profile.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Mi Perfil</span>
                </x-nav-link>
            </div>
        @endif

        @if($isClient)
            <div class="space-y-1">
                <p class="px-3 text-[10px] uppercase tracking-widest text-gold font-black mb-2 opacity-80">Mi Cuenta</p>
                <x-nav-link :href="route('client.appointments.index')" :active="request()->routeIs('client.appointments.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Mis Citas</span>
                </x-nav-link>
                <x-nav-link :href="route('client.barberos.index')" :active="request()->routeIs('client.barberos.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121"/><circle cx="17" cy="7" r="3"/></svg>
                    <span>Nuestros Barberos</span>
                </x-nav-link>
                <x-nav-link :href="route('client.facturas.index')" :active="request()->routeIs('client.facturas.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Mis Facturas</span>
                </x-nav-link>
            </div>
        @endif
    </div>

    <!-- User Footer Section (Fixed) -->
    <div :class="open ? 'block' : 'hidden'" class="hidden space-y-4 pt-4 lg:block border-t border-white/10 flex-shrink-0">
         <!-- Dark Mode Toggle y Keyboard Shortcuts -->
         <div class="px-2 mb-2 flex items-center gap-2 justify-between">
             <x-dark-mode-toggle />
             <x-keyboard-shortcuts-help />
         </div>

         <!-- Shortcut Hint -->
         <div class="px-2 mb-2 hidden lg:block">
            <div class="flex items-center justify-between rounded-lg bg-white/5 px-2 py-1.5 border border-white/5">
                <span class="text-[9px] font-black uppercase text-muted tracking-widest">Atajos</span>
                <div class="flex items-center gap-1">
                    <kbd class="px-1 py-0.5 rounded border border-white/10 bg-black text-[8px] font-bold text-gold">Ctrl</kbd>
                    <span class="text-[8px] text-muted">+</span>
                    <kbd class="px-1 py-0.5 rounded border border-white/10 bg-black text-[8px] font-bold text-gold">K</kbd>
                </div>
            </div>
         </div>

         <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="flex items-center gap-2 w-full justify-between">
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
        </x-nav-link>

        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 px-2 rounded-xl py-2 hover:bg-white/5 transition-colors group">
            <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-gold/30 to-gold/10 border border-gold/20 flex items-center justify-center text-[11px] font-black text-gold shrink-0 group-hover:from-gold/50 group-hover:to-gold/20 transition-all">
                {{ strtoupper(substr($user?->name ?? 'U', 0, 2)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="truncate text-xs font-bold text-white">{{ $user?->name }}</p>
                <p class="truncate text-[9px] font-bold text-muted uppercase tracking-wider">
                    @if($isAdmin) Administrador
                    @elseif($isBarber) Barbero
                    @elseif($isReception) Recepcionista
                    @else Cliente
                    @endif
                </p>
            </div>
            <svg class="h-3.5 w-3.5 text-muted group-hover:text-gold transition-colors shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </a>
        
        <form method="POST" action="{{ route('logout') }}" class="px-2">
            @csrf
            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-[10px] font-black uppercase tracking-widest text-white transition hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/20">
                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Cerrar Sesion
            </button>
        </form>
    </div>
</nav>
