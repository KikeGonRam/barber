<?php

namespace Tests\Feature;

use App\Exceptions\Domain\PackageException;
use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\ClientPackage;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\Package\PackageService;
use App\Services\Payment\CashCloseService;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Paquetes prepagados (roadmap P1, ver PackageService). Cubre la compra en
 * efectivo, el canje contra el cobro de una cita, el guard de mismo
 * cliente/servicio, el decremento atómico y la expiración.
 */
class PackageServiceTest extends TestCase
{
    private PackageService $packages;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packages = app(PackageService::class);
        $this->payments = app(PaymentService::class);
    }

    protected function tearDown(): void
    {
        ClientPackage::query()->delete();
        ServicePackage::query()->delete();
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makePackageSetup(int $usos = 5, float $precio = 1000, ?int $vigenciaDias = null): array
    {
        $client = Client::create(['telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 250, 'duracion_min' => 30, 'activo' => true]);
        $package = ServicePackage::create([
            'nombre' => '5 cortes',
            'service_id' => (string) $service->id,
            'cantidad_usos' => $usos,
            'precio' => $precio,
            'vigencia_dias' => $vigenciaDias,
            'activo' => true,
        ]);

        return compact('client', 'service', 'package');
    }

    public function test_purchase_cash_creates_client_package_with_full_uses(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup();

        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());

        $this->assertSame(5, $clientPackage->usos_totales);
        $this->assertSame(5, $clientPackage->usos_restantes);
        $this->assertEquals(1000.0, (float) $clientPackage->precio_pagado);
        $this->assertSame('efectivo', $clientPackage->metodo_pago);
        $this->assertSame(ClientPackage::ESTADO_ACTIVO, $clientPackage->estado);
        $this->assertNull($clientPackage->expira_en);
    }

    public function test_purchase_sets_expiration_when_package_has_vigencia(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup(vigenciaDias: 30);

        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());

        $this->assertNotNull($clientPackage->expira_en);
        $this->assertTrue($clientPackage->expira_en->isAfter(now()->addDays(29)));
    }

    public function test_purchase_is_rejected_when_package_is_inactive(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup();
        $package->update(['activo' => false]);

        $this->expectException(PackageException::class);
        $this->packages->purchaseCash($client, $package, (string) Str::uuid());
    }

    private function makeAppointmentFor(Client $client, Service $service, string $estado = 'confirmada'): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(2)->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => $estado,
        ]);
    }

    public function test_redeem_decrements_uses_and_completes_the_appointment_at_zero_cost(): void
    {
        Notification::fake();

        ['client' => $client, 'service' => $service, 'package' => $package] = $this->makePackageSetup();
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $appointment = $this->makeAppointmentFor($client, $service);

        $payment = $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 0,
            'metodo_pago' => 'efectivo',
            'usar_paquete_id' => (string) $clientPackage->id,
        ], (string) Str::uuid());

        $this->assertEquals(0.0, (float) $payment->monto);
        $this->assertSame((string) $clientPackage->id, $payment->client_package_id);
        $this->assertSame('completada', Appointment::find($appointment->id)->estado);
        $this->assertSame(4, ClientPackage::find($clientPackage->id)->usos_restantes);
    }

    public function test_redeem_marks_package_agotado_after_last_use(): void
    {
        ['client' => $client, 'service' => $service, 'package' => $package] = $this->makePackageSetup(usos: 1);
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $appointment = $this->makeAppointmentFor($client, $service);

        $this->packages->redeem($clientPackage, $appointment);

        $fresh = ClientPackage::find($clientPackage->id);
        $this->assertSame(0, $fresh->usos_restantes);
        $this->assertSame(ClientPackage::ESTADO_AGOTADO, $fresh->estado);
    }

    public function test_redeem_throws_when_no_uses_remain(): void
    {
        ['client' => $client, 'service' => $service, 'package' => $package] = $this->makePackageSetup(usos: 1);
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $appointment = $this->makeAppointmentFor($client, $service);

        $this->packages->redeem($clientPackage, $appointment);

        $this->expectException(PackageException::class);
        $this->packages->redeem($clientPackage->fresh(), $this->makeAppointmentFor($client, $service));
    }

    public function test_redeem_throws_when_package_belongs_to_another_client(): void
    {
        ['client' => $owner, 'service' => $service, 'package' => $package] = $this->makePackageSetup();
        $clientPackage = $this->packages->purchaseCash($owner, $package, (string) Str::uuid());

        $otherClient = Client::create(['telefono' => '5559998888', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $appointment = $this->makeAppointmentFor($otherClient, $service);

        $this->expectException(PackageException::class);
        $this->packages->redeem($clientPackage, $appointment);
    }

    public function test_redeem_throws_when_appointment_service_does_not_match(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup();
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());

        $otherService = Service::create(['nombre' => 'Barba', 'precio' => 100, 'duracion_min' => 15, 'activo' => true]);
        $appointment = $this->makeAppointmentFor($client, $otherService);

        $this->expectException(PackageException::class);
        $this->packages->redeem($clientPackage, $appointment);
    }

    public function test_redeem_throws_when_package_expired(): void
    {
        ['client' => $client, 'service' => $service, 'package' => $package] = $this->makePackageSetup(vigenciaDias: 10);
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $clientPackage->update(['expira_en' => now()->subDay()]);
        $appointment = $this->makeAppointmentFor($client, $service);

        $this->expectException(PackageException::class);
        $this->packages->redeem($clientPackage->fresh(), $appointment);
    }

    public function test_payment_service_rejects_combining_package_with_raffle_prize(): void
    {
        ['client' => $client, 'service' => $service, 'package' => $package] = $this->makePackageSetup();
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $appointment = $this->makeAppointmentFor($client, $service);

        $this->expectException(PaymentException::class);
        $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 0,
            'metodo_pago' => 'efectivo',
            'usar_paquete_id' => (string) $clientPackage->id,
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());
    }

    public function test_expire_stale_marks_past_dated_active_packages(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup(vigenciaDias: 10);
        $clientPackage = $this->packages->purchaseCash($client, $package, (string) Str::uuid());
        $clientPackage->update(['expira_en' => now()->subDay()]);

        $count = $this->packages->expireStale();

        $this->assertSame(1, $count);
        $this->assertSame(ClientPackage::ESTADO_EXPIRADO, ClientPackage::find($clientPackage->id)->estado);
    }

    public function test_cash_close_includes_package_purchases_in_the_daily_total(): void
    {
        ['client' => $client, 'package' => $package] = $this->makePackageSetup(precio: 1500);
        $this->packages->purchaseCash($client, $package, (string) Str::uuid());

        $cashClose = app(CashCloseService::class);
        $expected = $cashClose->expectedFor(Carbon::today());

        $this->assertSame(1, $expected['paquetes']);
        $this->assertEquals(1500.0, $expected['por_metodo']['efectivo']);
    }
}
