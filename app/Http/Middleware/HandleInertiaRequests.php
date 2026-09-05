<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Los widgets globales (toast, notificaciones) siguen viviendo en
            // Blade+Alpine (ver resources/views/app.blade.php); estas props
            // permiten que una página Vue dispare ese mismo toast con
            // window.dispatchEvent(new CustomEvent('notify', ...)) en vez de
            // reimplementar el componente. Ver AppLayout.vue.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('errors')?->first(),
            ],
        ];
    }
}
