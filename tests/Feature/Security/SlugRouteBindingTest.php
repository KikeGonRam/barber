<?php

namespace Tests\Feature\Security;

use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the UrlGenerationException incident: Barber, Client
 * and Service all bind routes by `slug` (see App\Traits\HasSlug). A record
 * with a null/empty slug must never crash a listing page — the row should
 * simply hide its slug-bound action links instead of throwing.
 */
class SlugRouteBindingTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_barber_gets_a_slug_automatically_on_creation(): void
    {
        $barber = Barber::factory()->create();

        $this->assertNotEmpty($barber->slug);
    }

    public function test_client_gets_a_slug_automatically_on_creation(): void
    {
        $client = Client::factory()->create();

        $this->assertNotEmpty($client->slug);
    }

    public function test_service_gets_a_slug_automatically_on_creation(): void
    {
        $service = Service::factory()->create();

        $this->assertNotEmpty($service->slug);
    }

    public function test_public_barber_profile_route_resolves_via_slug(): void
    {
        $barber = Barber::factory()->create();

        $response = $this->get(route('barbers.public.show', $barber));

        $response->assertOk();
    }

    public function test_social_feed_does_not_crash_when_a_barber_has_no_slug(): void
    {
        $barber = Barber::factory()->create();
        $barber->slug = null;
        $barber->saveQuietly();

        $response = $this->get('/descubrir');

        $response->assertOk();
    }

    public function test_admin_clients_index_does_not_crash_when_a_client_has_no_slug(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $client = Client::factory()->create();
        $client->slug = null;
        $client->saveQuietly();

        $response = $this->actingAs($admin)->get('/clients');

        $response->assertOk();
        $response->assertDontSee(route('clients.show', ['client' => (string) $client->id]), false);
    }

    public function test_admin_services_index_does_not_crash_when_a_service_has_no_slug(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $service = Service::factory()->create();
        $service->slug = null;
        $service->saveQuietly();

        $response = $this->actingAs($admin)->get('/services');

        $response->assertOk();
    }

    public function test_admin_barbers_index_does_not_crash_when_a_barber_has_no_slug(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $barber = Barber::factory()->create();
        $barber->slug = null;
        $barber->saveQuietly();

        $response = $this->actingAs($admin)->get('/barbers');

        $response->assertOk();
    }
}
