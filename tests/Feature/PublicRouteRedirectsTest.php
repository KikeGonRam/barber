<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión: /equipo/{barber}, /servicios, /notifications y
 * /notifications/preferences dejaron de renderizar vistas Blade el
 * 2026-09-09 -- ahora redirigen a sus equivalentes en frontend-urban (Nuxt),
 * que ya tienen paridad funcional completa. Ninguna requiere sesión: Nuxt
 * resuelve su propia auth con el token Bearer, no con la cookie de sesión
 * de Laravel.
 */
class PublicRouteRedirectsTest extends TestCase
{
    protected function tearDown(): void
    {
        Barber::query()->delete();
        User::query()->delete();

        parent::tearDown();
    }

    public function test_services_catalog_redirects_to_frontend(): void
    {
        $response = $this->get('/servicios');

        $response->assertRedirect(config('app.frontend_url').'/servicios');
    }

    public function test_barber_public_profile_redirects_to_frontend_with_slug(): void
    {
        $barberUser = User::create(['name' => 'Barbero Redirect', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $barber = Barber::create(['user_id' => (string) $barberUser->id, 'nombre' => 'Barbero Redirect', 'activo' => true]);

        $response = $this->get('/equipo/'.$barber->slug);

        $response->assertRedirect(config('app.frontend_url').'/equipo/'.$barber->slug);
    }

    public function test_notifications_index_redirects_to_frontend_without_requiring_a_session(): void
    {
        $response = $this->get('/notifications');

        $response->assertRedirect(config('app.frontend_url').'/notifications');
    }

    public function test_notifications_preferences_redirects_to_frontend_without_requiring_a_session(): void
    {
        $response = $this->get('/notifications/preferences');

        $response->assertRedirect(config('app.frontend_url').'/notifications');
    }
}
