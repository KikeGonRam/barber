<?php

namespace Tests\Feature\Clients;

use App\Models\Client;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class ClientManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_admin_can_view_clients_index(): void
    {
        Client::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/clients')->assertOk();
    }

    public function test_admin_can_create_a_new_client(): void
    {
        $response = $this->actingAs($this->admin())->post('/clients', [
            'name' => 'Cliente Nuevo',
            'email' => 'clientenuevo@example.com',
            'telefono' => '+521234567890',
        ]);

        $response->assertRedirect(route('clients.index'));
        $user = User::where('email', 'clientenuevo@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull($user->clientProfile);
    }

    public function test_store_requires_a_unique_email(): void
    {
        $existing = User::factory()->create();

        $response = $this->actingAs($this->admin())->post('/clients', [
            'name' => 'Duplicado',
            'email' => $existing->email,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_admin_can_view_a_client_profile(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('clients.show', $client));

        $response->assertOk();
    }

    public function test_admin_can_update_a_client(): void
    {
        $client = Client::factory()->create();
        $client->load('user');

        $response = $this->actingAs($this->admin())->put(route('clients.update', $client), [
            'name' => 'Nombre Editado',
            'email' => $client->user->email,
            'telefono' => $client->telefono,
        ]);

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseHas('users', ['_id' => $client->user_id, 'name' => 'Nombre Editado']);
    }

    public function test_admin_can_delete_a_client(): void
    {
        $client = Client::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('clients.destroy', $client));

        $response->assertRedirect(route('clients.index'));
        $this->assertDatabaseMissing('clients', ['_id' => $client->id]);
    }

    public function test_barbero_cannot_manage_clients(): void
    {
        $barbero = User::factory()->create();
        $barbero->assignRole('barbero');

        $this->actingAs($barbero)->get('/clients')->assertForbidden();
    }

    public function test_guest_is_redirected_from_client_management(): void
    {
        $this->get('/clients')->assertRedirect('/login');
    }
}
