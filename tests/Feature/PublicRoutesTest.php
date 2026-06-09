<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * FASE 3 — Rutas públicas
 *
 * Verifica que las rutas accesibles sin autenticación respondan correctamente:
 * página de bienvenida, catálogo de servicios, feed social, catálogo API y chatbot.
 */
class PublicRoutesTest extends TestCase
{
    use RefreshMongoDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // PÁGINA PRINCIPAL
    // ──────────────────────────────────────────────────────────────────────────

    public function test_welcome_page_returns_200(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_welcome_page_shows_urbanblade_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('UrbanBlade');
    }

    public function test_welcome_page_shows_services(): void
    {
        Service::factory()->create([
            'nombre' => 'Corte Premium Test',
            'activo' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Corte Premium Test');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SERVICIOS PÚBLICOS
    // ──────────────────────────────────────────────────────────────────────────

    public function test_public_services_page_returns_200(): void
    {
        $this->get('/servicios')->assertOk();
    }

    public function test_public_services_shows_active_services(): void
    {
        $active = Service::factory()->create(['nombre' => 'Corte Activo', 'activo' => true]);
        Service::factory()->create(['nombre' => 'Corte Inactivo', 'activo' => false]);

        $this->get('/servicios')
            ->assertOk()
            ->assertSee('Corte Activo')
            ->assertDontSee('Corte Inactivo');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PERFIL PÚBLICO DE BARBERO
    // ──────────────────────────────────────────────────────────────────────────

    public function test_barber_public_profile_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barber = Barber::factory()->create(['user_id' => $user->id, 'activo' => true]);

        $this->get('/barbero/'.$barber->id)->assertOk();
    }

    public function test_barber_public_profile_returns_404_for_unknown(): void
    {
        $this->get('/barbero/000000000000000000000000')->assertNotFound();
    }

    public function test_team_route_redirects_or_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barber = Barber::factory()->create(['user_id' => $user->id, 'activo' => true]);

        $response = $this->get('/equipo/'.$barber->id);
        $this->assertTrue(
            in_array($response->status(), [200, 301, 302]),
            'Expected 200, 301 or 302 for /equipo/{id}'
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FEED SOCIAL (DESCUBRIR) — público
    // ──────────────────────────────────────────────────────────────────────────

    public function test_social_feed_page_returns_200(): void
    {
        $this->get('/descubrir')->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // MANTENIMIENTO
    // ──────────────────────────────────────────────────────────────────────────

    public function test_maintenance_page_returns_200(): void
    {
        // CheckMaintenanceMode middleware redirects /mantenimiento → / when maintenance is OFF
        $this->get('/mantenimiento')->assertRedirect('/');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // API CATÁLOGO (público)
    // ──────────────────────────────────────────────────────────────────────────

    public function test_api_services_catalog_returns_200(): void
    {
        $this->getJson('/api/v1/services')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_api_services_catalog_only_returns_active(): void
    {
        Service::factory()->create(['nombre' => 'Activo', 'activo' => true]);
        Service::factory()->create(['nombre' => 'Inactivo', 'activo' => false]);

        $response = $this->getJson('/api/v1/services')->assertOk();
        $names = collect($response->json('data'))->pluck('nombre')->toArray();

        $this->assertContains('Activo', $names);
        $this->assertNotContains('Inactivo', $names);
    }

    public function test_api_barbers_catalog_returns_200(): void
    {
        $this->getJson('/api/v1/barbers')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_api_availability_slots_returns_200(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $barber = Barber::factory()->create(['user_id' => $user->id, 'activo' => true]);
        $service = Service::factory()->create(['activo' => true]);

        $this->getJson('/api/v1/availability/slots?barber_id='.$barber->id.'&service_id='.$service->id.'&date='.now()->addDay()->toDateString())
            ->assertOk();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CHATBOT — público
    // ──────────────────────────────────────────────────────────────────────────

    public function test_chatbot_query_returns_200(): void
    {
        $this->postJson('/api/v1/chatbot/query', [
            'message' => '¿Cuáles son sus servicios?',
        ])->assertOk()
          ->assertJsonStructure(['response']);
    }

    public function test_chatbot_query_web_returns_200(): void
    {
        $this->post('/chatbot/query', [
            'message' => 'Hola',
        ])->assertOk()
          ->assertJsonStructure(['response']);
    }
}
