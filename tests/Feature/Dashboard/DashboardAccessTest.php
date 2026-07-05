<?php

namespace Tests\Feature\Dashboard;

use App\Models\Barber;
use App\Models\Client;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_administrador_sees_the_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('administrador');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('adminMode', true);
    }

    public function test_recepcionista_sees_the_reception_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('recepcionista');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('isReceptionMode', true);
    }

    public function test_barbero_with_a_profile_sees_the_barber_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('barbero');
        Barber::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('isBarberMode', true);
    }

    public function test_barbero_without_a_profile_falls_back_to_the_default_view(): void
    {
        $user = User::factory()->create();
        $user->assignRole('barbero');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('isBarberMode', false);
    }

    public function test_cliente_gets_a_client_profile_created_automatically(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('isClientMode', true);
        $this->assertNotNull($user->fresh()->clientProfile);
    }

    public function test_cliente_with_existing_profile_sees_the_client_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cliente');
        Client::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('isClientMode', true);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
