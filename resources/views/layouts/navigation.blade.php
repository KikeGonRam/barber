@php
    $user = auth()->user();
    $isAdmin = $user?->hasRole('administrador');
    $isReception = $user?->hasRole('recepcionista');
    $isBarber = $user?->hasRole('barbero');
    $isClient = $user?->hasRole('cliente');
    $unread = $user?->unreadNotifications()->count() ?? 0;
@endphp

<nav x-data="{ open: false }" class="ui-panel m-4 p-3 lg:m-6 lg:h-[calc(100vh-3rem)] lg:sticky lg:top-6">
    <div class="flex items-center justify-between border-b border-[#666] px-2 pb-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 text-sm font-semibold tracking-wide text-[#f2f2f2]">
            <x-application-logo class="h-8 w-8 fill-current text-[#f2f2f2]" />
            <div>
                <span class="block">Barbershop</span>
                <span class="text-[11px] font-normal tracking-normal text-[#cfcfcf]">Panel operativo</span>
            </div>
        </a>
        <button @click="open = !open" class="inline-flex rounded-md border border-[#666] p-2 text-[#f2f2f2] lg:hidden" aria-label="Abrir menu">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <div :class="open ? 'block' : 'hidden'" class="hidden pt-4 lg:block">
        <div class="space-y-1">
            <p class="px-3 pb-1 pt-1 text-[11px] uppercase tracking-wider text-[#bdbdbd]">General</p>
            <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" /></svg>
                <span>Dashboard</span>
            </x-nav-link>

            @if($isAdmin || $isReception)
                <p class="px-3 pb-1 pt-3 text-[11px] uppercase tracking-wider text-[#bdbdbd]">Operacion</p>
                <x-nav-link :href="route('clients.index')" :active="request()->routeIs('clients.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
                    <span>Clientes</span>
                </x-nav-link>

                <x-nav-link :href="route('appointments.index')" :active="request()->routeIs('appointments.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/></svg>
                    <span>Citas</span>
                </x-nav-link>

                <x-nav-link :href="route('payments.index')" :active="request()->routeIs('payments.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2.5" y="6" width="19" height="12" rx="2"/><path d="M2.5 10h19M7 14h3"/></svg>
                    <span>Pagos</span>
                </x-nav-link>

                <x-nav-link :href="route('inventory.movements.index')" :active="request()->routeIs('inventory.movements.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 7h16v10H4z"/><path d="M9 7V5h6v2M8 12h8"/></svg>
                    <span>Movimientos</span>
                </x-nav-link>
            @endif

            @if($isBarber)
                <p class="px-3 pb-1 pt-3 text-[11px] uppercase tracking-wider text-[#bdbdbd]">Barbero</p>
                <x-nav-link :href="route('barber.agenda')" :active="request()->routeIs('barber.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 4h12M9 4v4m6-4v4M5 10h14v10H5z"/></svg>
                    <span>Mi agenda</span>
                </x-nav-link>
            @endif

            @if($isClient)
                <p class="px-3 pb-1 pt-3 text-[11px] uppercase tracking-wider text-[#bdbdbd]">Cliente</p>
                <x-nav-link :href="route('client.appointments.index')" :active="request()->routeIs('client.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
                    <span>Mis citas</span>
                </x-nav-link>
            @endif

            @if($isAdmin)
                <p class="px-3 pb-1 pt-3 text-[11px] uppercase tracking-wider text-[#bdbdbd]">Gestion</p>
                <x-nav-link :href="route('barbers.index')" :active="request()->routeIs('barbers.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 4l8 8M9 12l-2 8M16 4l-8 8M15 12l2 8"/></svg>
                    <span>Barberos</span>
                </x-nav-link>

                <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="10" r="2.5"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0"/><path d="M14 19a4.5 4.5 0 0 1 6.5-4"/></svg>
                    <span>Usuarios</span>
                </x-nav-link>

                <x-nav-link :href="route('services.index')" :active="request()->routeIs('services.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                    <span>Servicios</span>
                </x-nav-link>

                <x-nav-link :href="route('inventory.products.index')" :active="request()->routeIs('inventory.products.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 7v10l9 4 9-4V7"/></svg>
                    <span>Productos</span>
                </x-nav-link>

                <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 20h16M7 16V8M12 16V4M17 16v-6"/></svg>
                    <span>Reportes</span>
                </x-nav-link>

                <x-nav-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 15.5A3.5 3.5 0 1 0 12 8.5a3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1 1 0 0 0 .2 1.1l.1.1a1.8 1.8 0 1 1-2.6 2.6l-.1-.1a1 1 0 0 0-1.1-.2 1 1 0 0 0-.6.9V20a1.8 1.8 0 1 1-3.6 0v-.1a1 1 0 0 0-.6-.9 1 1 0 0 0-1.1.2l-.1.1a1.8 1.8 0 0 1-2.6-2.6l.1-.1a1 1 0 0 0 .2-1.1 1 1 0 0 0-.9-.6H4a1.8 1.8 0 1 1 0-3.6h.1a1 1 0 0 0 .9-.6 1 1 0 0 0-.2-1.1l-.1-.1a1.8 1.8 0 0 1 2.6-2.6l.1.1a1 1 0 0 0 1.1.2h.1a1 1 0 0 0 .6-.9V4a1.8 1.8 0 1 1 3.6 0v.1a1 1 0 0 0 .6.9 1 1 0 0 0 1.1-.2l.1-.1a1.8 1.8 0 1 1 2.6 2.6l-.1.1a1 1 0 0 0-.2 1.1v.1a1 1 0 0 0 .9.6H20a1.8 1.8 0 1 1 0 3.6h-.1a1 1 0 0 0-.9.6Z"/></svg>
                    <span>Configuracion</span>
                </x-nav-link>

                <x-nav-link :href="route('logs.index')" :active="request()->routeIs('logs.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 4h14v16H5z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>
                    <span>Logs</span>
                </x-nav-link>
            @endif

            <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 18H5l1.5-2.5V11a5.5 5.5 0 0 1 11 0v4.5L19 18h-4"/><path d="M10 19a2 2 0 0 0 4 0"/></svg>
                <span class="flex items-center gap-2">
                    Notificaciones
                    @if($unread > 0)
                        <span class="rounded-full bg-[#f0f0f0] px-2 py-[1px] text-[10px] font-semibold text-[#1f1f1f]">{{ $unread }}</span>
                    @endif
                </span>
            </x-nav-link>
        </div>

        <div class="mt-4 border-t border-[#666] pt-4">
            <div class="px-3 pb-3 text-xs text-[#c9c9c9]">
                <div class="font-medium text-[#f2f2f2]">{{ $user?->name }}</div>
                <div>{{ $user?->email }}</div>
            </div>
            <div class="space-y-1">
                <x-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3"/><path d="M4 20a8 8 0 0 1 16 0"/></svg>
                    <span>Perfil</span>
                </x-nav-link>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mt-3 px-2">
                @csrf
                <button type="submit" class="ui-btn w-full justify-center">Cerrar sesion</button>
            </form>
        </div>
    </div>
</nav>
