<?php

namespace App\Helpers;

use App\Models\User;
use App\Services\Cart\CartService;

/**
 * Fuente única de verdad para la navegación (sidebar desktop/tablet + drawer
 * móvil + bottom-nav móvil). Antes cada superficie mantenía su propia lista
 * de enlaces a mano y ya habían divergido (ver auditoría de la barra lateral).
 */
class NavigationMenu
{
    private const ICONS = [
        'dashboard' => '<path d="M3 12l9-8 9 8v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z" />',
        'wall' => '<path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />',
        'appointments' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'calendar' => '<path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/><circle cx="12" cy="15" r="2"/>',
        'clients' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'payments' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />',
        'orders' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 9l2 2 4-4"/>',
        'movements' => '<path d="M20 12V8H6a2 2 0 0 1-2-2c0-1.1.9-2 2-2h12v4"/><path d="M4 6v12a2 2 0 0 0 2 2h14v-4"/><path d="M18 12a2 2 0 0 0-2 2c0 1.1.9 2 2 2h4v-4h-4z"/>',
        'barbers' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'users' => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
        'services' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'products' => '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"/>',
        'reports' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>',
        'campaigns' => '<path d="M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z"/>',
        'logs' => '<polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>',
        'agenda' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'schedule' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'profile' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'my_appointments' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'cart' => '<path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'barbers_client' => '<path stroke-linecap="round" stroke-linejoin="round" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758L5 19m0-14l4.121 4.121"/><circle cx="17" cy="7" r="3"/>',
        'invoices' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        'notifications' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    ];

    /**
     * Secciones completas para el sidebar (desktop/tablet/drawer móvil).
     */
    public static function sections(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $isAdmin = $user->hasRole('administrador');
        $isReception = $user->hasRole('recepcionista');
        $isBarber = $user->hasRole('barbero');
        $isClient = $user->hasRole('cliente');

        $sections = [];

        $sections[] = [
            'title' => 'Principal',
            'items' => array_filter([
                self::item('Dashboard', 'dashboard', 'dashboard', ['dashboard'], primary: true),
                self::item('Muro Inspiración', 'social.feed', 'wall', ['social.feed']),
            ]),
        ];

        if ($isAdmin || $isReception) {
            $sections[] = [
                'title' => 'Operacion',
                'items' => array_filter([
                    self::item('Citas', 'appointments.index', 'appointments', ['appointments.index', 'appointments.create', 'appointments.edit'], primary: true),
                    self::item('Calendario', 'appointments.calendar', 'calendar', ['appointments.calendar*']),
                    self::item('Clientes', 'clients.index', 'clients', ['clients.*'], primary: $isAdmin),
                    self::item('Pagos', 'payments.index', 'payments', ['payments.*'], primary: true),
                    self::item('Pedidos', 'orders.index', 'orders', ['orders.*'], primary: $isReception),
                    self::item('Movimientos', 'inventory.movements.index', 'movements', ['inventory.movements.*']),
                ]),
            ];
        }

        if ($isAdmin) {
            $sections[] = [
                'title' => 'Gestion',
                'items' => array_filter([
                    self::item('Barberos', 'barbers.index', 'barbers', ['barbers.*']),
                    self::item('Usuarios', 'users.index', 'users', ['users.*']),
                    self::item('Servicios', 'services.index', 'services', ['services.*']),
                    self::item('Productos', 'inventory.products.index', 'products', ['inventory.products.*']),
                    self::item('Configuracion', 'settings.edit', 'settings', ['settings.*']),
                ]),
            ];
            $sections[] = [
                'title' => 'Analisis',
                'items' => array_filter([
                    self::item('Reportes', 'reports.index', 'reports', ['reports.*']),
                    self::item('Campanas', 'campaigns.index', 'campaigns', ['campaigns.*']),
                    self::item('Logs', 'logs.index', 'logs', ['logs.*']),
                ]),
            ];
        }

        if ($isBarber) {
            $sections[] = [
                'title' => 'Mi Espacio',
                'items' => array_filter([
                    self::item('Mi Agenda', 'barber.agenda', 'agenda', ['barber.agenda'], primary: true),
                    self::item('Mi Portafolio', 'barber.portfolio.index', 'wall', ['barber.portfolio.*'], primary: true),
                    self::item('Mi Horario', 'barber.schedule.edit', 'schedule', ['barber.schedule.*'], primary: true),
                    self::item('Mi Perfil', 'barber.profile.edit', 'profile', ['barber.profile.*']),
                ]),
            ];
        }

        if ($isClient) {
            $navCart = app(CartService::class)->count();

            $sections[] = [
                'title' => 'Mi Cuenta',
                'items' => array_filter([
                    self::item('Mis Citas', 'client.appointments.index', 'my_appointments', ['client.appointments.*'], primary: true),
                    self::item('Tienda', 'client.tienda.index', 'products', ['client.tienda.*']),
                    self::item('Carrito', 'client.carrito.index', 'cart', ['client.carrito.*'], primary: true, badge: $navCart > 0 ? $navCart : null),
                    self::item('Mis Pedidos', 'client.pedidos.index', 'orders', ['client.pedidos.*']),
                    self::item('Nuestros Barberos', 'client.barberos.index', 'barbers_client', ['client.barberos.*'], primary: true),
                    self::item('Mis Facturas', 'client.facturas.index', 'invoices', ['client.facturas.*']),
                ]),
            ];
        }

        // Enriquecer cada sección con metadata para el acordeón del sidebar:
        // - key: id estable (para recordar abierto/cerrado en localStorage)
        // - collapsible: "Principal" siempre visible; el resto se pliega
        // - active: true si la ruta actual cae en alguno de sus ítems
        // - count: cuántos ítems tiene (badge del encabezado)
        return array_map(function (array $section) {
            $items = array_values($section['items']);

            return [
                'title'       => $section['title'],
                'items'       => $items,
                'key'         => \Illuminate\Support\Str::slug($section['title']),
                'collapsible' => $section['title'] !== 'Principal',
                'active'      => collect($items)->contains('active', true),
                'count'       => count($items),
            ];
        }, $sections);
    }

    /**
     * Ítems marcados "primary" (hasta 4), en el orden en que deben
     * aparecer en el bottom-nav móvil. El botón "Más" (abre el drawer)
     * se agrega aparte en la vista porque no es una ruta.
     */
    public static function primary(?User $user): array
    {
        $items = [];

        foreach (self::sections($user) as $section) {
            foreach ($section['items'] as $item) {
                if ($item['primary']) {
                    $items[] = $item;
                }
            }
        }

        return array_slice($items, 0, 4);
    }

    private static function item(string $label, string $route, string $icon, array $activePatterns, bool $primary = false, ?int $badge = null): ?array
    {
        if (! \Illuminate\Support\Facades\Route::has($route)) {
            return null;
        }

        return [
            'label' => $label,
            'href' => route($route),
            'icon' => self::ICONS[$icon] ?? '',
            'active' => collect($activePatterns)->contains(fn ($pattern) => request()->routeIs($pattern)),
            'primary' => $primary,
            'badge' => $badge,
        ];
    }
}
