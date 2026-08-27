<?php

namespace Tests\Feature;

use App\Exceptions\Domain\PaymentException;
use App\Jobs\RunOcrOnComprobante;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Payment\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre el cobro directo, el flujo de
 * comprobante de transferencia (subida/aprobación/rechazo) y el efecto
 * compartido de completeCharge() (cita completada + PDF generado).
 */
class PaymentServiceIntegrationTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PaymentService::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeChargeableAppointment(array $overrides = []): Appointment
    {
        $barber = Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
        $client = Client::create(['telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 300, 'duracion_min' => 30, 'activo' => true]);

        return Appointment::create(array_merge([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ], $overrides));
    }

    // UploadedFile::fake()->image() necesita la extension GD (no instalada
    // en el contenedor); create() genera un archivo dummy sin depender de GD.
    private function fakeReceipt(string $name = 'comprobante.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'image/jpeg');
    }

    public function test_create_charges_directly_completes_appointment_and_generates_pdf(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();

        $payment = $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'propina' => 50,
        ], (string) Str::uuid());

        $this->assertSame(Payment::ESTADO_VERIFICADO, $payment->estado);
        $this->assertNotEmpty($payment->comprobante_pdf);
        Storage::disk('public')->assertExists($payment->comprobante_pdf);

        $freshAppointment = Appointment::find($appointment->id);
        $this->assertSame('completada', $freshAppointment->estado);
        $this->assertEquals(500, (float) $freshAppointment->precio_cobrado);

        $this->assertNotNull(Payment::find($payment->id));
    }

    public function test_create_throws_when_appointment_is_not_chargeable(): void
    {
        $appointment = $this->makeChargeableAppointment(['estado' => 'pendiente']);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());
    }

    public function test_create_throws_when_appointment_already_has_a_payment(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'tarjeta',
        ], (string) Str::uuid());
    }

    public function test_upload_transfer_receipt_creates_pending_payment_and_dispatches_ocr_job(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();

        $payment = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $this->assertSame(Payment::ESTADO_PENDIENTE_VERIFICACION, $payment->estado);
        $this->assertSame('transferencia', $payment->metodo_pago);
        Storage::disk('public')->assertExists($payment->comprobante_cliente);

        // No debe completar la cita todavía (queda pendiente de revisión).
        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);

        Queue::assertPushed(RunOcrOnComprobante::class, fn ($job) => $job->paymentId === (string) $payment->id);
    }

    public function test_upload_transfer_receipt_throws_when_a_payment_already_exists(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt('a.jpg'), (string) Str::uuid());

        $this->expectException(PaymentException::class);

        $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt('b.jpg'), (string) Str::uuid());
    }

    public function test_approve_transfer_completes_the_charge(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $payment = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $approved = $this->service->approveTransfer($payment, (string) Str::uuid());

        $this->assertSame(Payment::ESTADO_VERIFICADO, $approved->estado);
        $this->assertNotEmpty($approved->comprobante_pdf);
        Storage::disk('public')->assertExists($approved->comprobante_pdf);
        $this->assertSame('completada', Appointment::find($appointment->id)->estado);
    }

    public function test_approve_transfer_throws_when_already_reviewed(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $payment = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());
        $this->service->approveTransfer($payment, (string) Str::uuid());

        $this->expectException(PaymentException::class);

        $this->service->approveTransfer($payment->fresh(), (string) Str::uuid());
    }

    public function test_reject_transfer_marks_rechazado_without_completing_the_appointment(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $payment = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $rejected = $this->service->rejectTransfer($payment, (string) Str::uuid(), 'Monto no coincide');

        $this->assertSame(Payment::ESTADO_RECHAZADO, $rejected->estado);
        $this->assertSame('Monto no coincide', $rejected->motivo_rechazo);
        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_a_rejected_transfer_does_not_block_uploading_a_new_receipt(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $first = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt('a.jpg'), (string) Str::uuid());
        $this->service->rejectTransfer($first, (string) Str::uuid(), 'Comprobante ilegible');

        // existsForAppointment() excluye explícitamente los rechazados.
        $second = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt('b.jpg'), (string) Str::uuid());

        $this->assertSame(Payment::ESTADO_PENDIENTE_VERIFICACION, $second->estado);
    }
}
