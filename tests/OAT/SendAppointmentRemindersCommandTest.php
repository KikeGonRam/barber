<?php

namespace Tests\OAT;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Notifications\Appointment\AppointmentNotification;
use App\Services\Messaging\MessagingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * appointments:send-reminders — recordatorio de 24 h (todas las citas de mañana) y de 2 h (citas que empiezan entre 90 y
 * 150 minutos desde ahora). Cada cita se avisa una sola vez gracias a sus marcas reminder_*_sent_at. El reloj se fija
 * a las 10:00 para que las ventanas sean deterministas.
 */
class SendAppointmentRemindersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 10:00:00');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        // Appointment usa borrado lógico: forceDelete, o quedarían citas huérfanas para data:verify-integrity.
        Appointment::withTrashed()->forceDelete();
        foreach ([Barber::class, Client::class, Service::class] as $modelo) {
            $modelo::query()->delete();
        }
        User::withTrashed()->forceDelete();

        parent::tearDown();
    }

    /** Cita para un cliente con usuario; $preferencias activa SMS/WhatsApp. */
    private function cita(string $fecha, string $hora, string $estado = 'confirmada', array $preferencias = []): array
    {
        $usuario = User::create(['name' => 'Cliente Recordatorio', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $usuario->forceFill(['notification_preferences' => $preferencias])->save();
        $cliente = Client::create(['user_id' => (string) $usuario->id, 'telefono' => '5551234567', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barbero = Barber::create(['nombre' => 'Barbero Recordatorio', 'activo' => true]);
        $servicio = Service::create(['nombre' => 'Corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);

        $cita = Appointment::create([
            'client_id' => (string) $cliente->id, 'barber_id' => (string) $barbero->id, 'service_id' => (string) $servicio->id,
            'fecha' => $fecha, 'hora_inicio' => $hora, 'hora_fin' => '23:59:00', 'estado' => $estado,
        ]);

        return [$usuario, $cita];
    }

    public function test_reminds_tomorrows_open_appointments_only_once(): void
    {
        [$usuarioA, $citaA] = $this->cita('2026-11-04', '09:00:00', 'pendiente');
        [$usuarioB, $citaB] = $this->cita('2026-11-04', '17:00:00', 'confirmada');
        [$cancelada, $citaCancelada] = $this->cita('2026-11-04', '12:00:00', 'cancelada');
        [$otroDia, $citaOtroDia] = $this->cita('2026-11-05', '12:00:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($usuarioA, AppointmentNotification::class, 1);
        Notification::assertSentToTimes($usuarioB, AppointmentNotification::class, 1);
        Notification::assertNotSentTo($cancelada, AppointmentNotification::class);
        Notification::assertNotSentTo($otroDia, AppointmentNotification::class);
        $this->assertNotNull($citaA->fresh()->getAttribute('reminder_24h_sent_at'));
        $this->assertNotNull($citaB->fresh()->getAttribute('reminder_24h_sent_at'));
        $this->assertNull($citaCancelada->fresh()->getAttribute('reminder_24h_sent_at'));
        $this->assertNull($citaOtroDia->fresh()->getAttribute('reminder_24h_sent_at'));
    }

    public function test_reminds_two_hours_before_inside_the_90_to_150_minute_window(): void
    {
        [$dentro, $citaDentro] = $this->cita('2026-11-03', '12:00:00');
        [$limite, $citaLimite] = $this->cita('2026-11-03', '11:30:00');
        [$pronto, $citaPronto] = $this->cita('2026-11-03', '11:00:00');
        [$lejos, $citaLejos] = $this->cita('2026-11-03', '15:00:00');

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($dentro, AppointmentNotification::class, 1);
        Notification::assertSentToTimes($limite, AppointmentNotification::class, 1);
        Notification::assertNotSentTo($pronto, AppointmentNotification::class);
        Notification::assertNotSentTo($lejos, AppointmentNotification::class);
        $this->assertNotNull($citaDentro->fresh()->getAttribute('reminder_2h_sent_at'));
        $this->assertNotNull($citaLimite->fresh()->getAttribute('reminder_2h_sent_at'));
        $this->assertNull($citaPronto->fresh()->getAttribute('reminder_2h_sent_at'));
        $this->assertNull($citaLejos->fresh()->getAttribute('reminder_2h_sent_at'));
    }

    public function test_skips_appointments_whose_client_has_no_user(): void
    {
        $cliente = Client::create(['telefono' => '5550000000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barbero = Barber::create(['nombre' => 'Barbero sin cliente', 'activo' => true]);
        $servicio = Service::create(['nombre' => 'Corte', 'precio' => 150, 'duracion_min' => 30, 'activo' => true]);
        $cita = Appointment::create([
            'client_id' => (string) $cliente->id, 'barber_id' => (string) $barbero->id, 'service_id' => (string) $servicio->id,
            'fecha' => '2026-11-04', 'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00', 'estado' => 'confirmada',
        ]);

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($cita->fresh()->getAttribute('reminder_24h_sent_at'));
    }

    public function test_sends_direct_messages_only_through_the_channels_the_user_enabled(): void
    {
        $this->cita('2026-11-04', '10:00:00', 'confirmada', ['sms' => true, 'whatsapp' => false]);
        $mensajeria = $this->mock(MessagingService::class);
        $mensajeria->shouldReceive('sendSms')->once()->with('5551234567', 'Recordatorio: tu cita es mañana.');
        $mensajeria->shouldNotReceive('sendWhatsapp');

        $this->artisan('appointments:send-reminders')->assertSuccessful();
    }
}
