<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Models\User;
use Tests\Support\RefreshMongoDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshMongoDatabase;

    public function test_administrador_can_create_service(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('services.store'), [
                'nombre' => 'Corte degradado',
                'categoria' => 'corte',
                'precio' => 250,
                'duracion_min' => 45,
                'descripcion' => 'Corte premium con acabado.',
                'activo' => 1,
            ])
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', [
            'nombre' => 'Corte degradado',
            'categoria' => 'corte',
            'duracion_min' => 45,
            'activo' => 1,
        ]);
    }

    public function test_administrador_can_update_service_and_set_inactive(): void
    {
        $admin = $this->makeAdmin();

        $service = Service::query()->create([
            'nombre' => 'Barba básica',
            'categoria' => 'barba',
            'precio' => 150,
            'duracion_min' => 20,
            'activo' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('services.update', $service), [
                'nombre' => 'Barba completa',
                'categoria' => 'barba',
                'precio' => 180,
                'duracion_min' => 30,
                'descripcion' => 'Perfilado completo',
                'activo' => 0,
            ])
            ->assertRedirect(route('services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'nombre' => 'Barba completa',
            'activo' => 0,
        ]);
    }

    public function test_services_index_filters_by_active_status(): void
    {
        $admin = $this->makeAdmin();

        Service::query()->create([
            'nombre' => 'Servicio Activo',
            'categoria' => 'corte',
            'precio' => 100,
            'duracion_min' => 15,
            'activo' => true,
        ]);

        Service::query()->create([
            'nombre' => 'Servicio Inactivo',
            'categoria' => 'corte',
            'precio' => 100,
            'duracion_min' => 15,
            'activo' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('services.index', ['activo' => '0']));

        $response->assertOk();
        $response->assertSee('Servicio Inactivo');
        $response->assertDontSee('Servicio Activo');
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $role = Role::query()->firstOrCreate([
            'name' => 'administrador',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
