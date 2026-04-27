<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Repositories\Eloquent\AppointmentRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\ServiceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepositoriesTest extends TestCase
{
    use RefreshDatabase;

    // ── BaseRepository via ProductRepository ───────────────────────────────

    public function test_base_repository_all_returns_all_records(): void
    {
        Product::factory()->count(3)->create();

        $repo = app(ProductRepository::class);
        $results = $repo->all();

        $this->assertCount(3, $results);
    }

    public function test_base_repository_find_returns_model_by_id(): void
    {
        $product = Product::factory()->create(['nombre' => 'Shampoo Test']);

        $repo = app(ProductRepository::class);
        $found = $repo->find($product->id);

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
    }

    public function test_base_repository_find_returns_null_for_missing_id(): void
    {
        $repo = app(ProductRepository::class);

        $this->assertNull($repo->find(999));
    }

    public function test_base_repository_create_persists_record(): void
    {
        $repo = app(ProductRepository::class);

        $product = $repo->create([
            'nombre' => 'Cera Mate',
            'categoria' => 'insumo',
            'descripcion' => 'Para el cabello',
            'precio_compra' => 50.00,
            'precio_venta' => 120.00,
            'stock_actual' => 10,
            'stock_minimo' => 2,
            'tipo' => 'insumo_trabajo',
        ]);

        $this->assertDatabaseHas('products', ['nombre' => 'Cera Mate']);
        $this->assertSame('Cera Mate', $product->nombre);
    }

    public function test_base_repository_update_modifies_record(): void
    {
        $product = Product::factory()->create(['nombre' => 'Viejo nombre']);
        $repo = app(ProductRepository::class);

        $updated = $repo->update($product->id, ['nombre' => 'Nuevo nombre']);

        $this->assertTrue($updated);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'nombre' => 'Nuevo nombre']);
    }

    public function test_base_repository_delete_removes_record(): void
    {
        $product = Product::factory()->create();
        $repo = app(ProductRepository::class);

        $deleted = $repo->delete($product->id);

        $this->assertTrue($deleted);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // ── ProductRepository ──────────────────────────────────────────────────

    public function test_product_repository_low_stock_count_counts_products_at_or_below_minimum(): void
    {
        Product::factory()->create(['stock_actual' => 2, 'stock_minimo' => 5]);
        Product::factory()->create(['stock_actual' => 5, 'stock_minimo' => 5]);
        Product::factory()->create(['stock_actual' => 10, 'stock_minimo' => 5]);

        $repo = app(ProductRepository::class);

        $this->assertSame(2, $repo->lowStockCount());
    }

    public function test_product_repository_paginate_with_filters_filters_by_categoria(): void
    {
        Product::factory()->create(['categoria' => 'venta']);
        Product::factory()->create(['categoria' => 'insumo']);

        $repo = app(ProductRepository::class);
        $results = $repo->paginateWithFilters(['categoria' => 'venta'], 15);

        $this->assertSame(1, $results->total());
        $this->assertSame('venta', $results->items()[0]->categoria);
    }

    public function test_product_repository_paginate_with_filters_filters_by_tipo(): void
    {
        Product::factory()->create(['tipo' => 'venta_cliente']);
        Product::factory()->create(['tipo' => 'insumo_trabajo']);

        $repo = app(ProductRepository::class);
        $results = $repo->paginateWithFilters(['tipo' => 'insumo_trabajo'], 15);

        $this->assertSame(1, $results->total());
        $this->assertSame('insumo_trabajo', $results->items()[0]->tipo);
    }

    public function test_product_repository_paginate_with_no_filters_returns_all(): void
    {
        Product::factory()->count(3)->create();

        $repo = app(ProductRepository::class);
        $results = $repo->paginateWithFilters([], 15);

        $this->assertSame(3, $results->total());
    }

    // ── AppointmentRepository ──────────────────────────────────────────────

    public function test_appointment_repository_get_by_barber_and_date_returns_ordered_appointments(): void
    {
        $barber = Barber::factory()->create();
        $date = now()->addDays(5)->toDateString();
        $client = Client::factory()->create();
        $service = Service::factory()->create();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'confirmada',
        ]);

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);

        $repo = app(AppointmentRepository::class);
        $results = $repo->getByBarberAndDate($barber->id, $date);

        $this->assertCount(2, $results);
        $this->assertSame('09:00:00', $results[0]->hora_inicio);
        $this->assertSame('11:00:00', $results[1]->hora_inicio);
    }

    public function test_appointment_repository_has_overlap_ignores_specified_appointment(): void
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create();
        $date = now()->addDays(4)->toDateString();

        $existing = Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $repo = app(AppointmentRepository::class);

        // Without ignore: overlap detected
        $this->assertTrue($repo->hasOverlap($barber->id, $date, '10:00:00', '10:30:00'));

        // With ignore: no overlap (same appointment excluded)
        $this->assertFalse($repo->hasOverlap($barber->id, $date, '10:00:00', '10:30:00', $existing->id));
    }

    public function test_appointment_repository_get_by_barber_and_date_ignores_other_barbers(): void
    {
        $barber1 = Barber::factory()->create();
        $barber2 = Barber::factory()->create();
        $date = now()->addDays(3)->toDateString();
        $client = Client::factory()->create();
        $service = Service::factory()->create();

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber2->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $repo = app(AppointmentRepository::class);
        $results = $repo->getByBarberAndDate($barber1->id, $date);

        $this->assertCount(0, $results);
    }

    // ── PaymentRepository ──────────────────────────────────────────────────

    public function test_payment_repository_exists_for_appointment_returns_true_when_payment_exists(): void
    {
        $payment = Payment::factory()->create();
        $repo = app(PaymentRepository::class);

        $this->assertTrue($repo->existsForAppointment($payment->appointment_id));
    }

    public function test_payment_repository_exists_for_appointment_returns_false_when_no_payment(): void
    {
        $appointment = Appointment::factory()->create();
        $repo = app(PaymentRepository::class);

        $this->assertFalse($repo->existsForAppointment($appointment->id));
    }

    public function test_payment_repository_paginate_with_filters_filters_by_metodo_pago(): void
    {
        Payment::factory()->create(['metodo_pago' => 'efectivo']);
        Payment::factory()->create(['metodo_pago' => 'tarjeta']);
        Payment::factory()->create(['metodo_pago' => 'efectivo']);

        $repo = app(PaymentRepository::class);
        $results = $repo->paginateWithFilters(['metodo_pago' => 'efectivo'], 15);

        $this->assertSame(2, $results->total());
    }

    // ── ServiceRepository ──────────────────────────────────────────────────

    public function test_service_repository_get_categories_returns_distinct_sorted_categories(): void
    {
        Service::factory()->create(['categoria' => 'corte']);
        Service::factory()->create(['categoria' => 'barba']);
        Service::factory()->create(['categoria' => 'corte']);

        $repo = app(ServiceRepository::class);
        $categories = $repo->getCategories();

        $this->assertSame(['barba', 'corte'], $categories);
    }

    public function test_service_repository_paginate_with_filters_filters_by_categoria(): void
    {
        Service::factory()->create(['categoria' => 'corte', 'activo' => true]);
        Service::factory()->create(['categoria' => 'barba', 'activo' => true]);

        $repo = app(ServiceRepository::class);
        $results = $repo->paginateWithFilters(['categoria' => 'barba'], 15);

        $this->assertSame(1, $results->total());
    }

    public function test_service_repository_paginate_with_filters_filters_by_activo(): void
    {
        Service::factory()->create(['activo' => true]);
        Service::factory()->create(['activo' => false]);

        $repo = app(ServiceRepository::class);
        $results = $repo->paginateWithFilters(['activo' => '1'], 15);

        $this->assertSame(1, $results->total());
        $this->assertTrue((bool) $results->items()[0]->activo);
    }
}
