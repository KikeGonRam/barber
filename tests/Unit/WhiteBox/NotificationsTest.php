<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\PaymentReceiptNotification;
use Tests\Support\RefreshMongoDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshMongoDatabase;

    private function makeAppointmentNotification(Appointment $appointment): AppointmentNotification
    {
        return new AppointmentNotification(
            appointment: $appointment,
            subject: 'Confirmación de cita',
            title: 'Tu cita fue registrada',
            message: 'Tu cita fue confirmada en el sistema.',
        );
    }

    private function makeAppointment(): Appointment
    {
        $barber = Barber::factory()->create();
        $client = Client::factory()->create();
        $service = Service::factory()->create(['nombre' => 'Corte clásico']);

        return Appointment::factory()->create([
            'barber_id' => $barber->id,
            'client_id' => $client->id,
            'service_id' => $service->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);
    }

    public function test_appointment_notification_via_returns_database_when_user_wants_in_app(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => false, 'sms' => false, 'whatsapp' => false],
        ]);

        $notification = $this->makeAppointmentNotification($this->makeAppointment());
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_appointment_notification_via_returns_mail_when_user_wants_email(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => false, 'email' => true, 'sms' => false, 'whatsapp' => false],
        ]);

        $notification = $this->makeAppointmentNotification($this->makeAppointment());
        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
        $this->assertNotContains('database', $channels);
    }

    public function test_appointment_notification_via_returns_both_channels_when_user_wants_both(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => true, 'sms' => false, 'whatsapp' => false],
        ]);

        $notification = $this->makeAppointmentNotification($this->makeAppointment());
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains('mail', $channels);
    }

    public function test_appointment_notification_via_falls_back_to_database_when_no_channels(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => false, 'email' => false, 'sms' => false, 'whatsapp' => false],
        ]);

        $notification = $this->makeAppointmentNotification($this->makeAppointment());
        $channels = $notification->via($user);

        $this->assertSame(['database'], $channels);
    }

    public function test_appointment_notification_to_array_contains_required_fields(): void
    {
        $user = User::factory()->create();
        $appointment = $this->makeAppointment();
        $notification = $this->makeAppointmentNotification($appointment);

        $payload = $notification->toArray($user);

        $this->assertSame('appointment', $payload['type']);
        $this->assertSame($appointment->id, $payload['appointment_id']);
        $this->assertSame('Confirmación de cita', $payload['subject']);
        $this->assertSame('Tu cita fue registrada', $payload['title']);
        $this->assertSame('Tu cita fue confirmada en el sistema.', $payload['message']);
        $this->assertArrayHasKey('fecha', $payload);
        $this->assertArrayHasKey('hora_inicio', $payload);
        $this->assertArrayHasKey('hora_fin', $payload);
    }

    public function test_payment_receipt_notification_via_returns_database_for_in_app_preference(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => true, 'email' => false, 'sms' => false, 'whatsapp' => false],
        ]);

        $payment = Payment::factory()->create();
        $notification = new PaymentReceiptNotification($payment);
        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
        $this->assertNotContains('mail', $channels);
    }

    public function test_payment_receipt_notification_via_falls_back_to_database(): void
    {
        $user = User::factory()->create();
        Client::factory()->create([
            'user_id' => $user->id,
            'preferencias_notificacion' => ['in_app' => false, 'email' => false, 'sms' => false, 'whatsapp' => false],
        ]);

        $payment = Payment::factory()->create();
        $notification = new PaymentReceiptNotification($payment);
        $channels = $notification->via($user);

        $this->assertSame(['database'], $channels);
    }

    public function test_payment_receipt_notification_to_array_contains_required_fields(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'monto' => '350.00',
            'propina' => '50.00',
            'metodo_pago' => 'efectivo',
        ]);

        $notification = new PaymentReceiptNotification($payment);
        $payload = $notification->toArray($user);

        $this->assertSame('payment', $payload['type']);
        $this->assertSame($payment->id, $payload['payment_id']);
        $this->assertSame($payment->appointment_id, $payload['appointment_id']);
        $this->assertSame(350.0, $payload['monto']);
        $this->assertSame(50.0, $payload['propina']);
        $this->assertSame('efectivo', $payload['metodo_pago']);
        $this->assertStringContainsString((string) $payment->id, $payload['message']);
    }
}
