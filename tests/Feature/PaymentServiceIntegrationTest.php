<?php

namespace Tests\Feature;

use App\Exceptions\Domain\PaymentException;
use App\Jobs\RunOcrOnComprobante;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\RaffleResult;
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
        RaffleResult::query()->delete();
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
        // El monto cobrado es el precio real del servicio (300), no el 500
        // enviado en el payload — ver test_create_ignores_a_manipulated_monto...
        $this->assertEquals(300, (float) $freshAppointment->precio_cobrado);

        $this->assertNotNull(Payment::find($payment->id));
    }

    public function test_create_ignores_a_manipulated_monto_and_uses_the_real_service_price(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment(); // servicio de $300

        // Simula un request manipulado mandando un monto muy por debajo del real.
        $payment = $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 0.01,
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());

        $this->assertEquals(300.0, (float) $payment->monto); // precio real del servicio, NO 0.01
        $this->assertEquals(300.0, (float) Appointment::find($appointment->id)->precio_cobrado);
    }

    public function test_create_applies_the_client_level_discount_automatically(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        // El nivel se lee del cliente en cobro, no de lo que se manda en el payload.
        $appointment->client->update(['nivel' => 'vip']); // 10% de descuento

        $payment = $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500, // ignorado: el precio real del servicio es 300
            'metodo_pago' => 'efectivo',
        ], (string) Str::uuid());

        $this->assertEquals(270.0, (float) $payment->monto); // 300 - 10%
        $this->assertEquals(270.0, (float) Appointment::find($appointment->id)->precio_cobrado);
    }

    public function test_create_redeems_points_on_top_of_the_level_discount(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $appointment->client->update(['nivel' => 'vip', 'puntos' => 100]); // 10% desc. + 100 pts disponibles

        $payment = $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500, // ignorado: precio real 300 -> 270 tras el 10% de nivel
            'metodo_pago' => 'efectivo',
            'puntos_canjeados' => 60,
        ], (string) Str::uuid());

        $this->assertEquals(210.0, (float) $payment->monto); // 270 - 60
        $this->assertSame(60, (int) $payment->puntos_canjeados);
        // 100 - 60 canjeados + 10 otorgados por completar esta misma cita (completeCharge -> awardCitaPoints).
        $this->assertSame(50, (int) $appointment->client->fresh()->puntos);
    }

    public function test_create_throws_when_puntos_canjeados_exceeds_the_fifty_percent_cap(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        // Sin descuento de nivel: precio real = 300, tope 50% = 150. El cliente tiene de sobra.
        $appointment->client->update(['puntos' => 1000]);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'puntos_canjeados' => 151,
        ], (string) Str::uuid());
    }

    public function test_create_throws_when_puntos_canjeados_exceeds_client_balance(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        // Tope del 50% (250) es mayor que el saldo real (10) -> el saldo es lo que limita.
        $appointment->client->update(['puntos' => 10]);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'puntos_canjeados' => 11,
        ], (string) Str::uuid());
    }

    public function test_create_does_not_deduct_points_when_the_cap_is_exceeded(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $appointment->client->update(['puntos' => 10]);

        try {
            $this->service->create([
                'appointment_id' => (string) $appointment->id,
                'monto' => 500,
                'metodo_pago' => 'efectivo',
                'puntos_canjeados' => 11,
            ], (string) Str::uuid());
        } catch (PaymentException) {
            // esperado
        }

        // El saldo no debe haberse tocado, y no debe haber quedado un pago a medias.
        $this->assertSame(10, (int) $appointment->client->fresh()->puntos);
        $this->assertNull(Payment::query()->where('appointment_id', (string) $appointment->id)->first());
    }

    public function test_create_applies_raffle_prize_as_full_discount_and_claims_it(): void
    {
        Notification::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $prize = RaffleResult::create([
            'client_id' => (string) $appointment->client_id,
            'mes' => now()->subMonth()->format('Y-m'),
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->addDays(30),
        ]);

        $payment = $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());

        $this->assertEquals(0.0, (float) $payment->monto);
        $this->assertSame((string) $prize->id, $payment->raffle_result_id);

        $freshPrize = RaffleResult::find($prize->id);
        $this->assertNotNull($freshPrize->reclamado_en);
        $this->assertSame((string) $appointment->id, $freshPrize->appointment_id);
        $this->assertFalse($freshPrize->isRedeemable());
    }

    public function test_create_throws_when_using_raffle_prize_the_client_does_not_have(): void
    {
        $appointment = $this->makeChargeableAppointment();

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());
    }

    public function test_create_throws_when_using_an_already_claimed_raffle_prize(): void
    {
        $appointment = $this->makeChargeableAppointment();
        RaffleResult::create([
            'client_id' => (string) $appointment->client_id,
            'mes' => now()->subMonth()->format('Y-m'),
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->addDays(30),
            'reclamado_en' => now()->subDay(),
        ]);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());
    }

    public function test_create_throws_when_using_an_expired_raffle_prize(): void
    {
        $appointment = $this->makeChargeableAppointment();
        RaffleResult::create([
            'client_id' => (string) $appointment->client_id,
            'mes' => now()->subMonths(3)->format('Y-m'),
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->subDay(),
        ]);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'usar_premio_rifa' => true,
        ], (string) Str::uuid());
    }

    public function test_create_throws_when_combining_raffle_prize_with_point_redemption(): void
    {
        $appointment = $this->makeChargeableAppointment();
        RaffleResult::create([
            'client_id' => (string) $appointment->client_id,
            'mes' => now()->subMonth()->format('Y-m'),
            'premio' => 'Corte premium gratis',
            'nivel_ganador' => 'vip',
            'vence_en' => now()->addDays(30),
        ]);

        $this->expectException(PaymentException::class);

        $this->service->create([
            'appointment_id' => (string) $appointment->id,
            'monto' => 500,
            'metodo_pago' => 'efectivo',
            'usar_premio_rifa' => true,
            'puntos_canjeados' => 5,
        ], (string) Str::uuid());
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

    public function test_upload_transfer_receipt_applies_the_client_level_discount(): void
    {
        Notification::fake();
        Queue::fake();
        Storage::fake('public');

        $appointment = $this->makeChargeableAppointment();
        $appointment->client->update(['nivel' => 'regular']); // 5% de descuento
        $appointment->refresh();

        $payment = $this->service->uploadTransferReceipt($appointment, $this->fakeReceipt(), (string) Str::uuid());

        $this->assertEquals(285.0, (float) $payment->monto); // 300 - 5%
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
