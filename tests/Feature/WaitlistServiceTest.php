<?php

namespace Tests\Feature;

use App\Exceptions\Domain\WaitlistException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Client;
use App\Models\Service;
use App\Models\User;
use App\Models\Waitlist;
use App\Notifications\Appointment\AppointmentNotification;
use App\Services\Appointment\WaitlistService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lista de espera (roadmap P1, ver WaitlistService). Cubre el guard de "el
 * día debe estar realmente lleno", el duplicado, el aviso al liberarse un
 * horario, y la expiración de anotaciones con fecha ya pasada.
 */
class WaitlistServiceTest extends TestCase
{
    private WaitlistService $waitlist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->waitlist = app(WaitlistService::class);
    }

    protected function tearDown(): void
    {
        Waitlist::query()->delete();
        Appointment::withTrashed()->forceDelete();
        BarberSchedule::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeFullyBookedDay(): array
    {
        $client = Client::create(['telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber = Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 300, 'duracion_min' => 480, 'activo' => true]);
        $fecha = now()->addDays(5)->format('Y-m-d');

        // Horario del barbero exactamente igual a la duración del servicio
        // (8h): una sola cita del día completo lo deja sin huecos.
        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => now()->addDays(5)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_working' => true,
        ]);

        $occupant = Client::create(['telefono' => '5552220000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $appointment = Appointment::create([
            'client_id' => (string) $occupant->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $fecha,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
            'estado' => 'confirmada',
        ]);

        return compact('client', 'barber', 'service', 'fecha', 'appointment', 'occupant');
    }

    public function test_join_is_rejected_when_slots_are_still_available(): void
    {
        $client = Client::create(['telefono' => '5551110000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber = Barber::create(['nombre' => 'Barbero libre', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $fecha = now()->addDays(5)->format('Y-m-d');

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => now()->addDays(5)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_working' => true,
        ]);

        $this->expectException(WaitlistException::class);
        $this->waitlist->join($client, $barber, $service, $fecha);
    }

    public function test_join_succeeds_when_the_day_is_fully_booked(): void
    {
        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha] = $this->makeFullyBookedDay();

        $entry = $this->waitlist->join($client, $barber, $service, $fecha);

        $this->assertSame(Waitlist::ESTADO_ACTIVO, $entry->estado);
        $this->assertSame((string) $client->id, $entry->client_id);
    }

    public function test_joining_twice_is_rejected(): void
    {
        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha] = $this->makeFullyBookedDay();

        $this->waitlist->join($client, $barber, $service, $fecha);

        $this->expectException(WaitlistException::class);
        $this->waitlist->join($client, $barber, $service, $fecha);
    }

    public function test_notify_if_any_marks_entries_notified_and_sends_notification(): void
    {
        Notification::fake();

        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha, 'appointment' => $appointment] = $this->makeFullyBookedDay();
        $waitingUser = User::create(['name' => 'Cliente en espera', 'email' => Str::uuid().'@test.local', 'password' => 'password']);
        $client->update(['user_id' => (string) $waitingUser->id]);

        $entry = $this->waitlist->join($client->fresh(), $barber, $service, $fecha);

        // El ocupante cancela -> se libera el horario.
        $appointment->update(['estado' => 'cancelada']);
        $this->waitlist->notifyIfAny($appointment->fresh());

        $this->assertSame(Waitlist::ESTADO_NOTIFICADO, Waitlist::find($entry->id)->estado);
        Notification::assertSentTo($waitingUser, AppointmentNotification::class);
    }

    public function test_notify_if_any_ignores_entries_for_a_different_day(): void
    {
        Notification::fake();

        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha, 'appointment' => $appointment] = $this->makeFullyBookedDay();
        $entry = $this->waitlist->join($client, $barber, $service, $fecha);

        // Cita de OTRO día del mismo barbero/servicio se cancela -- no debe tocar la anotación.
        $otherAppointment = Appointment::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->addDays(20)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '17:00:00',
            'estado' => 'cancelada',
        ]);

        $this->waitlist->notifyIfAny($otherAppointment);

        $this->assertSame(Waitlist::ESTADO_ACTIVO, Waitlist::find($entry->id)->estado);
    }

    public function test_cancel_marks_entry_cancelled(): void
    {
        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha] = $this->makeFullyBookedDay();
        $entry = $this->waitlist->join($client, $barber, $service, $fecha);

        $this->waitlist->cancel($entry);

        $this->assertSame(Waitlist::ESTADO_CANCELADO, Waitlist::find($entry->id)->estado);
    }

    public function test_cancel_throws_when_entry_is_not_active(): void
    {
        ['client' => $client, 'barber' => $barber, 'service' => $service, 'fecha' => $fecha] = $this->makeFullyBookedDay();
        $entry = $this->waitlist->join($client, $barber, $service, $fecha);
        $this->waitlist->cancel($entry);

        $this->expectException(WaitlistException::class);
        $this->waitlist->cancel($entry->fresh());
    }

    public function test_expire_stale_marks_past_dated_entries_as_expired(): void
    {
        $client = Client::create(['telefono' => '5553330000', 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
        $barber = Barber::create(['nombre' => 'Barbero', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $entry = Waitlist::create([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => now()->subDays(2)->format('Y-m-d'),
            'estado' => Waitlist::ESTADO_ACTIVO,
        ]);

        $count = $this->waitlist->expireStale();

        $this->assertSame(1, $count);
        $this->assertSame(Waitlist::ESTADO_EXPIRADO, Waitlist::find($entry->id)->estado);
    }
}
