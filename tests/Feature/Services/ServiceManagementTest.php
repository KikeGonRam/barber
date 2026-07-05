<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('administrador');

        return $user;
    }

    public function test_public_services_page_only_shows_active_services(): void
    {
        $active = Service::factory()->create(['activo' => true, 'nombre' => 'Corte Activo Visible']);
        $inactive = Service::factory()->create(['activo' => false, 'nombre' => 'Servicio Oculto']);

        $response = $this->get('/servicios');

        $response->assertOk();
        $response->assertSee($active->nombre);
        $response->assertDontSee($inactive->nombre);
    }

    public function test_admin_can_view_services_index(): void
    {
        Service::factory()->count(2)->create();

        $this->actingAs($this->admin())->get('/services')->assertOk();
    }

    public function test_admin_can_create_a_service(): void
    {
        $response = $this->actingAs($this->admin())->post('/services', [
            'nombre' => 'Corte Ejecutivo',
            'categoria' => 'corte',
            'precio' => 250,
            'duracion_min' => 30,
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', ['nombre' => 'Corte Ejecutivo']);
    }

    public function test_store_requires_a_positive_price(): void
    {
        $response = $this->actingAs($this->admin())->post('/services', [
            'nombre' => 'Corte Gratis',
            'categoria' => 'corte',
            'precio' => -10,
            'duracion_min' => 30,
        ]);

        $response->assertSessionHasErrors('precio');
    }

    public function test_admin_can_update_a_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->admin())->put(route('services.update', $service), [
            'nombre' => 'Nombre Actualizado',
            'categoria' => $service->categoria,
            'precio' => $service->precio,
            'duracion_min' => $service->duracion_min,
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', ['_id' => $service->id, 'nombre' => 'Nombre Actualizado']);
    }

    public function test_admin_can_delete_a_service(): void
    {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->admin())->delete(route('services.destroy', $service));

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseMissing('services', ['_id' => $service->id]);
    }

    public function test_recepcionista_cannot_manage_services(): void
    {
        $recepcionista = User::factory()->create();
        $recepcionista->assignRole('recepcionista');

        $this->actingAs($recepcionista)->get('/services')->assertForbidden();
    }
}
