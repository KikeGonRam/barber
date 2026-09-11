<?php

namespace Tests\Feature;

use App\Exceptions\Domain\PaymentException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Payment\DepositService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Política anti-no-show (roadmap P1, ver DepositService). Cubre el cálculo
 * del requisito, la creación/aprobación/rechazo del depósito, que el cobro
 * final lo reste del total, y que una cancelación a tiempo lo reembolse.
 */
class DepositServiceTest extends TestCase
{
    private DepositService $deposits;

    private PaymentService $payments;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deposits = app(DepositService::class);
        $this->payments = app(PaymentService::class);
    }

    protected function tearDown(): void
    {
        Payment::query()->delete();
        Appointment::withTrashed()->forceDelete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();
        BarbershopSetting::query()->delete();

        parent::tearDown();
    }

    private function makeClient(): Client
    {
        return Client::create(['telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    private function makeService(float $precio = 300): Service
    {
        return Service::create(['nombre' => 'Corte clásico', 'precio' => $precio, 'duracion_min' => 30, 'activo' => true]);
    }

    private function makeBarber(): Barber
    {
        return Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
    }

    private function markNoShow(Client $client, Barber $barber, Service $service, \DateTimeInterface|string $fecha): void
    {
        Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'no_asistio',
        ]);
    }

    private function fakeReceipt(string $name = 'comprobante.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'image/jpeg');
    }

    public function test_requirement_is_false_below_threshold(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);

        // Solo 1 no-show, umbral por defecto es 2.
        $this->markNoShow($client, $barber, $service, now()->subDays(5));

        $result = $this->deposits->requirementFor($client, $service);

        $this->assertFalse($result['requerido']);
        $this->assertEquals(0.0, $result['monto']);
    }

    public function test_requirement_is_true_at_threshold_and_computes_percentage_amount(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);

        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'politica_cancelacion' => 24, 'deposito_no_show_umbral' => 2, 'deposito_no_show_porcentaje' => 50]);

        $this->markNoShow($client, $barber, $service, now()->subDays(5));
        $this->markNoShow($client, $barber, $service, now()->subDays(10));

        $result = $this->deposits->requirementFor($client, $service);

        $this->assertTrue($result['requerido']);
        $this->assertEquals(150.0, $result['monto']); // 50% de 300
    }

    public function test_requirement_ignores_no_shows_older_than_90_days(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);

        $this->markNoShow($client, $barber, $service, now()->subDays(100));
        $this->markNoShow($client, $barber, $service, now()->subDays(95));

        $result = $this->deposits->requirementFor($client, $service);

        $this->assertFalse($result['requerido']);
    }

    public function test_requirement_is_disabled_when_percentage_is_zero(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);

        BarbershopSetting::create(['nombre' => 'UrbanBlade', 'politica_cancelacion' => 24, 'deposito_no_show_umbral' => 2, 'deposito_no_show_porcentaje' => 0]);

        $this->markNoShow($client, $barber, $service, now()->subDays(5));
        $this->markNoShow($client, $barber, $service, now()->subDays(10));

        $result = $this->deposits->requirementFor($client, $service);

        $this->assertFalse($result['requerido']);
    }

    private function makePendingAppointmentRequiringDeposit(): Appointment
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);

        return Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
            'deposito_requerido' => true,
            'deposito_monto' => 150.0,
        ]);
    }

    public function test_upload_transfer_receipt_creates_pending_deposit_without_completing_appointment(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit();

        $payment = $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $this->assertTrue((bool) $payment->es_deposito);
        $this->assertSame(Payment::ESTADO_PENDIENTE_VERIFICACION, $payment->estado);
        $this->assertSame('pendiente', Appointment::find($appointment->id)->estado);
    }

    public function test_a_second_deposit_upload_is_rejected_while_one_is_pending(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit();
        $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt('a.jpg'), (string) Str::uuid());

        $this->expectException(PaymentException::class);
        $this->deposits->uploadTransferReceipt($appointment->fresh(), $this->fakeReceipt('b.jpg'), (string) Str::uuid());
    }

    public function test_upload_transfer_receipt_is_rejected_when_deposit_not_required(): void
    {
        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService(300);
        $appointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $this->expectException(PaymentException::class);
        $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());
    }

    public function test_approve_verifies_deposit_but_does_not_complete_appointment_or_award_points(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit();
        $payment = $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $approved = $this->deposits->approve($payment, (string) Str::uuid());

        $this->assertSame(Payment::ESTADO_VERIFICADO, $approved->estado);
        $this->assertSame('pendiente', Appointment::find($appointment->id)->estado);

        $client = Client::find($appointment->client_id);
        $this->assertEquals(0, $client->puntos);
    }

    public function test_reject_frees_the_appointment_for_a_new_deposit_attempt(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit();
        $payment = $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt('a.jpg'), (string) Str::uuid());

        $this->deposits->reject($payment, (string) Str::uuid(), 'Comprobante ilegible');

        // No debe lanzar: el rechazo liberó el slot (bloquea_cita=false).
        $second = $this->deposits->uploadTransferReceipt($appointment->fresh(), $this->fakeReceipt('b.jpg'), (string) Str::uuid());
        $this->assertSame(Payment::ESTADO_PENDIENTE_VERIFICACION, $second->estado);
    }

    public function test_final_charge_nets_the_verified_deposit_from_the_total(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit(); // servicio $300, deposito $150
        $payment = $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());
        $this->deposits->approve($payment, (string) Str::uuid());

        // El barbero aprueba la cita para poder cobrarla.
        Appointment::find($appointment->id)->update(['estado' => 'confirmada']);

        $finalPayment = $this->payments->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 300,
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());

        // 300 (precio) - 150 (deposito ya pagado) = 150.
        $this->assertEquals(150.0, (float) $finalPayment->monto);
        $this->assertSame('completada', Appointment::find($appointment->id)->estado);
    }

    public function test_refund_if_any_marks_a_transfer_deposit_as_reembolsado(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makePendingAppointmentRequiringDeposit();
        $payment = $this->deposits->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());
        $this->deposits->approve($payment, (string) Str::uuid());

        $this->deposits->refundIfAny($appointment->fresh());

        $this->assertSame(Payment::ESTADO_REEMBOLSADO, Payment::find($payment->id)->estado);
    }

    public function test_refund_if_any_is_a_no_op_when_there_is_no_deposit(): void
    {
        $appointment = $this->makePendingAppointmentRequiringDeposit();

        $this->deposits->refundIfAny($appointment);

        $this->assertSame(0, Payment::where('appointment_id', (string) $appointment->id)->count());
    }
}
