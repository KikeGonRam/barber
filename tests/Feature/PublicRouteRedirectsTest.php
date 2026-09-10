<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regresión: /, /equipo/{barber}, /servicios, /notifications,
 * /notifications/preferences, /profile, backups/database,
 * cliente/membresia/tarjeta, login, register, forgot-password y
 * reset-password/{token} dejaron de renderizar vistas Blade o exigir
 * sesión el 2026-09-09 -- ahora redirigen a sus equivalentes en
 * frontend-urban (Nuxt), que ya tienen paridad funcional completa. Ninguna
 * requiere sesión: Nuxt resuelve su propia auth con el token Bearer, no con
 * la cookie de sesión de Laravel.
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

    public function test_profile_redirects_to_frontend_without_requiring_a_session(): void
    {
        $response = $this->get('/profile');

        $response->assertRedirect(config('app.frontend_url').'/profile');
    }

    public function test_database_backup_redirects_to_frontend_settings_without_requiring_a_session(): void
    {
        $response = $this->get('/backups/database');

        $response->assertRedirect(config('app.frontend_url').'/settings');
    }

    public function test_membership_card_redirects_to_frontend_dashboard_without_requiring_a_session(): void
    {
        $response = $this->get('/cliente/membresia/tarjeta');

        $response->assertRedirect(config('app.frontend_url').'/dashboard');
    }

    public function test_landing_redirects_to_frontend(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(config('app.frontend_url'));
    }

    public function test_login_redirects_to_frontend(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect(config('app.frontend_url').'/login');
    }

    public function test_register_redirects_to_frontend(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect(config('app.frontend_url').'/register');
    }

    public function test_forgot_password_redirects_to_frontend(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertRedirect(config('app.frontend_url').'/forgot-password');
    }

    public function test_reset_password_redirects_to_frontend_with_the_token(): void
    {
        $response = $this->get('/reset-password/abc123');

        $response->assertRedirect(config('app.frontend_url').'/reset-password?token=abc123');
    }
}
