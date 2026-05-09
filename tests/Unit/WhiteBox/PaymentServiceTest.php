<?php

namespace Tests\Unit\WhiteBox;

use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->paymentService = app(PaymentService::class);
    }

    private function makeConfirmedAppointment(): Appointment
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['nombre' => 'Corte clásico', 'precio' => 200]);

        return Appointment::factory()->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'estado' => 'confirmada',
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
        ]);
    }

    public function test_create_throws_payment_exception_for_cancelled_appointment(): void
    {
        $appointment = $this->makeConfirmedAppointment();
        $appointment->update(['estado' => 'cancelada']);

        $creator = User::factory()->create();

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('No se puede registrar un pago para una cita cancelada o no asistida.');

        $this->paymentService->create([
            'appointment_id' => $appointment->id,
            'monto' => 200.00,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ], $creator->id);
    }

    public function test_create_throws_payment_exception_for_no_asistio_appointment(): void
    {
        $appointment = $this->makeConfirmedAppointment();
        $appointment->update(['estado' => 'no_asistio']);

        $creator = User::factory()->create();

        $this->expectException(PaymentException::class);

        $this->paymentService->create([
            'appointment_id' => $appointment->id,
            'monto' => 200.00,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ], $creator->id);
    }

    public function test_create_throws_payment_exception_when_payment_already_exists(): void
    {
        $appointment = $this->makeConfirmedAppointment();
        $creator = User::factory()->create();

        Payment::factory()->create([
            'appointment_id' => $appointment->id,
            'created_by' => $creator->id,
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('La cita ya tiene un pago registrado.');

        $this->paymentService->create([
            'appointment_id' => $appointment->id,
            'monto' => 200.00,
            'metodo_pago' => 'efectivo',
            'propina' => 0,
        ], $creator->id);
    }

    public function test_create_persists_payment_and_updates_appointment_to_completada(): void
    {
        $appointment = $this->makeConfirmedAppointment();
        $creator = User::factory()->create();

        $payment = $this->paymentService->create([
            'appointment_id' => $appointment->id,
            'monto' => 250.00,
            'metodo_pago' => 'tarjeta',
            'propina' => 30.00,
        ], $creator->id);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertDatabaseHas('payments', [
            'appointment_id' => $appointment->id,
            'metodo_pago' => 'tarjeta',
        ]);
        $appointment->refresh();
        $this->assertSame('completada', $appointment->estado);
    }

    public function test_list_returns_paginated_payments(): void
    {
        Payment::factory()->count(5)->create();

        $result = $this->paymentService->list([], 3);

        $this->assertSame(5, $result->total());
        $this->assertSame(3, $result->perPage());
    }

    public function test_list_filters_by_metodo_pago(): void
    {
        Payment::factory()->create(['metodo_pago' => 'efectivo']);
        Payment::factory()->create(['metodo_pago' => 'tarjeta']);
        Payment::factory()->create(['metodo_pago' => 'efectivo']);

        $result = $this->paymentService->list(['metodo_pago' => 'efectivo'], 15);

        $this->assertSame(2, $result->total());
    }
}
