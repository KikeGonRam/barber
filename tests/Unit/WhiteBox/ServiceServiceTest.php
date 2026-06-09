<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Service;
use App\Services\ServiceService;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class ServiceServiceTest extends TestCase
{
    use RefreshMongoDatabase;

    private ServiceService $serviceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceService = app(ServiceService::class);
    }

    public function test_list_returns_paginated_services(): void
    {
        Service::factory()->count(5)->create();

        $result = $this->serviceService->list([], 3);

        $this->assertSame(5, $result->total());
        $this->assertSame(3, $result->perPage());
    }

    public function test_list_filters_by_categoria(): void
    {
        Service::factory()->create(['categoria' => 'corte', 'activo' => true]);
        Service::factory()->create(['categoria' => 'barba', 'activo' => true]);
        Service::factory()->create(['categoria' => 'corte', 'activo' => true]);

        $result = $this->serviceService->list(['categoria' => 'corte'], 15);

        $this->assertSame(2, $result->total());
    }

    public function test_list_filters_by_activo(): void
    {
        Service::factory()->create(['activo' => true]);
        Service::factory()->create(['activo' => false]);

        $result = $this->serviceService->list(['activo' => '1'], 15);

        $this->assertSame(1, $result->total());
        $this->assertTrue((bool) $result->items()[0]->activo);
    }

    public function test_categories_returns_distinct_sorted_categories(): void
    {
        Service::factory()->create(['categoria' => 'combo']);
        Service::factory()->create(['categoria' => 'barba']);
        Service::factory()->create(['categoria' => 'combo']);
        Service::factory()->create(['categoria' => 'corte']);

        $categories = $this->serviceService->categories();

        $this->assertSame(['barba', 'combo', 'corte'], $categories);
    }

    public function test_categories_returns_empty_array_when_no_services(): void
    {
        $categories = $this->serviceService->categories();

        $this->assertSame([], $categories);
    }

    public function test_create_persists_and_returns_service(): void
    {
        $payload = [
            'nombre' => 'Corte degradado',
            'categoria' => 'corte',
            'precio' => 250.00,
            'duracion_min' => 45,
            'activo' => true,
        ];

        $service = $this->serviceService->create($payload);

        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame('Corte degradado', $service->nombre);
        $this->assertDatabaseHas('services', ['nombre' => 'Corte degradado']);
    }

    public function test_update_modifies_service(): void
    {
        $service = Service::factory()->create(['nombre' => 'Original', 'activo' => true]);

        $result = $this->serviceService->update($service, ['nombre' => 'Actualizado', 'activo' => false, 'categoria' => 'barba', 'precio' => 180, 'duracion_min' => 30]);

        $this->assertTrue($result);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'nombre' => 'Actualizado', 'activo' => 0]);
    }

    public function test_delete_removes_service(): void
    {
        $service = Service::factory()->create();

        $result = $this->serviceService->delete($service);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }
}
