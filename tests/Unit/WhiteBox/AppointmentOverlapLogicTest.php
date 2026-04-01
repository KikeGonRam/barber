<?php

namespace Tests\Unit\WhiteBox;

/**
 * Cubre pruebas de caja blanca sobre la logica interna de solapamientos
 * y control de stock en movimientos de inventario.
 */

use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AppointmentOverlapLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_range_1000_1030_does_not_overlap_with_1030_1100(): void
    {
        [$barber, $date] = $this->seedSingleAppointment('10:00:00', '10:30:00');

        $repo = app(AppointmentRepository::class);
        $overlap = $repo->hasOverlap($barber->id, $date, '10:30:00', '11:00:00');

        $this->assertFalse($overlap);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_range_1000_1030_overlaps_with_0945_1015(): void
    {
        [$barber, $date] = $this->seedSingleAppointment('10:00:00', '10:30:00');

        $repo = app(AppointmentRepository::class);
        $overlap = $repo->hasOverlap($barber->id, $date, '09:45:00', '10:15:00');

        $this->assertTrue($overlap);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_range_1000_1030_overlaps_with_0900_1100(): void
    {
        [$barber, $date] = $this->seedSingleAppointment('10:00:00', '10:30:00');

        $repo = app(AppointmentRepository::class);
        $overlap = $repo->hasOverlap($barber->id, $date, '09:00:00', '11:00:00');

        $this->assertTrue($overlap);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_range_1000_1030_overlaps_with_identical_range(): void
    {
        [$barber, $date] = $this->seedSingleAppointment('10:00:00', '10:30:00');

        $repo = app(AppointmentRepository::class);
        $overlap = $repo->hasOverlap($barber->id, $date, '10:00:00', '10:30:00');

        $this->assertTrue($overlap);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_cancelled_appointment_does_not_block_slot(): void
    {
        [$barber, $date] = $this->seedSingleAppointment('10:00:00', '10:30:00', 'cancelada');

        $repo = app(AppointmentRepository::class);
        $overlap = $repo->hasOverlap($barber->id, $date, '10:05:00', '10:20:00');

        $this->assertFalse($overlap);
        $this->assertDatabaseHas('appointments', ['estado' => 'cancelada']);
    }

    public function test_stock_zero_does_not_allow_output_movement(): void
    {
        $inventoryService = app(InventoryService::class);
        $user = $this->createInventoryActor();

        $product = Product::factory()->create([
            'stock_actual' => 0,
            'stock_minimo' => 1,
        ]);

        $this->expectException(InsufficientStockException::class);

        $inventoryService->registerMovement([
            'product_id' => $product->id,
            'tipo' => 'salida',
            'cantidad' => 1,
            'motivo' => 'Prueba salida sin stock',
        ], $user->id);
    }

    public function test_exact_stock_allows_exact_output_movement(): void
    {
        $inventoryService = app(InventoryService::class);
        $user = $this->createInventoryActor();

        $product = Product::factory()->create([
            'stock_actual' => 5,
            'stock_minimo' => 1,
        ]);

        $movement = $inventoryService->registerMovement([
            'product_id' => $product->id,
            'tipo' => 'salida',
            'cantidad' => 5,
            'motivo' => 'Salida exacta',
        ], $user->id);

        $product->refresh();

        $this->assertSame('salida', $movement->tipo);
        $this->assertSame(0, $product->stock_actual);
    }

    private function seedSingleAppointment(string $start, string $end, string $status = 'confirmada'): array
    {
        $barber = Barber::factory()->create(['activo' => true]);
        $client = Client::factory()->create();
        $service = Service::factory()->create(['activo' => true]);
        $date = now()->addDays(4)->toDateString();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => $start,
            'hora_fin' => $end,
            'estado' => $status,
        ]);

        return [$barber, $date];
    }

    private function createInventoryActor(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $role = Role::query()->firstOrCreate([
            'name' => 'recepcionista',
            'guard_name' => 'web',
        ]);

        $user->assignRole($role);

        return $user;
    }
}
