<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Services\Analytics\BarberCommissionService;
use Tests\TestCase;

/**
 * Comisiones de barberos (roadmap P1, ver BarberCommissionService). Cubre
 * que la base sea el precio de LISTA del servicio (no precio_cobrado, que
 * puede venir reducido por descuento/paquete/gift card/rifa), el % propio
 * de cada barbero, el filtro por estado/periodo, y las citas huérfanas.
 */
class BarberCommissionServiceTest extends TestCase
{
    private BarberCommissionService $commissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->commissions = app(BarberCommissionService::class);
    }

    protected function tearDown(): void
    {
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeClient(): Client
    {
        return Client::create(['telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    public function test_commission_is_based_on_service_list_price_not_precio_cobrado(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true, 'comision_pct' => 50]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        // La cita se pagó con descuento/paquete (precio_cobrado = 0), pero
        // el servicio de lista sigue costando 300 -- la comisión debe
        // calcularse sobre eso, no sobre lo que entró en caja.
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'completada',
            'precio_cobrado' => 0,
        ]);

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(1, $report['barberos']);
        $this->assertEquals(300.0, $report['barberos'][0]['total_generado']);
        $this->assertEquals(150.0, $report['barberos'][0]['comision_monto']); // 50% de 300
    }

    public function test_ignores_appointments_that_are_not_completed(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true, 'comision_pct' => 40]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        foreach (['pendiente', 'confirmada', 'cancelada', 'no_asistio'] as $i => $estado) {
            Appointment::create([
                'client_id' => (string) $client->id,
                'barber_id' => (string) $barber->id,
                'service_id' => (string) $service->id,
                'fecha' => now()->format('Y-m-d'),
                'hora_inicio' => sprintf('%02d:00:00', 10 + $i),
                'hora_fin' => sprintf('%02d:30:00', 10 + $i),
                'estado' => $estado,
            ]);
        }

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(0, $report['barberos']);
        $this->assertEquals(0.0, $report['total_generado']);
    }

    public function test_sums_multiple_completed_appointments_for_the_same_barber(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true, 'comision_pct' => 30]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 100, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        for ($i = 0; $i < 3; $i++) {
            Appointment::create([
                'client_id' => (string) $client->id,
                'barber_id' => (string) $barber->id,
                'service_id' => (string) $service->id,
                'fecha' => now()->format('Y-m-d'),
                'hora_inicio' => sprintf('%02d:00:00', 10 + $i),
                'hora_fin' => sprintf('%02d:30:00', 10 + $i),
                'estado' => 'completada',
            ]);
        }

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(3, $report['barberos'][0]['citas_completadas']);
        $this->assertEquals(300.0, $report['barberos'][0]['total_generado']);
        $this->assertEquals(90.0, $report['barberos'][0]['comision_monto']); // 30% de 300
    }

    public function test_reports_separately_per_barber_and_sorts_by_highest_commission(): void
    {
        $barberA = Barber::create(['nombre' => 'Barbero A', 'activo' => true, 'comision_pct' => 50]);
        $barberB = Barber::create(['nombre' => 'Barbero B', 'activo' => true, 'comision_pct' => 50]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barberA->id, 'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'completada',
        ]);
        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barberB->id, 'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '11:00:00', 'hora_fin' => '11:30:00', 'estado' => 'completada',
        ]);
        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barberB->id, 'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '12:00:00', 'hora_fin' => '12:30:00', 'estado' => 'completada',
        ]);

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(2, $report['barberos']);
        // Barbero B generó el doble (2 citas) -- debe salir primero.
        $this->assertSame((string) $barberB->id, $report['barberos'][0]['barber']['id']);
        // A: 200 generado * 50% = 100. B: 400 generado * 50% = 200. Total 300.
        $this->assertEquals(300.0, $report['total_comisiones']);
    }

    public function test_defaults_uncofigured_commission_percentage_to_zero(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero sin comisión configurada', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 500, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'completada',
        ]);

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertEquals(0.0, $report['barberos'][0]['comision_pct']);
        $this->assertEquals(0.0, $report['barberos'][0]['comision_monto']);
        $this->assertEquals(500.0, $report['barberos'][0]['total_generado']);
    }

    public function test_excludes_completed_appointments_outside_the_date_range(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true, 'comision_pct' => 50]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 100, 'duracion_min' => 30, 'activo' => true]);
        $client = $this->makeClient();

        Appointment::create([
            'client_id' => (string) $client->id, 'barber_id' => (string) $barber->id, 'service_id' => (string) $service->id,
            'fecha' => now()->subMonths(2)->format('Y-m-d'), 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'completada',
        ]);

        $report = $this->commissions->reportFor(now()->startOfMonth(), now()->endOfMonth());

        $this->assertCount(0, $report['barberos']);
    }
}
