<?php

namespace Tests\Feature;

use App\Exceptions\Domain\AppointmentConflictException;
use App\Exceptions\Domain\ClientAlreadyBookedException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Client;
use App\Models\Service;
use App\Services\Appointment\AppointmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre las dos reglas de negocio que
 * ensureNoOverlap() protege (cliente con doble cita el mismo día, barbero
 * con solape de horario) y el cálculo de slots disponibles.
 */
class AppointmentServiceIntegrationTest extends TestCase
{
    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AppointmentService::class);
    }

    protected function tearDown(): void
    {
        // barber_db_test es exclusiva de los tests; limpiar por completo
        // entre pruebas es más simple y seguro que rastrear ids uno a uno.
        Appointment::query()->delete();
        BarberSchedule::query()->delete();
        Barber::query()->delete();
        Client::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function makeBarber(): Barber
    {
        return Barber::create(['nombre' => 'Barbero de prueba', 'activo' => true]);
    }

    private function makeClient(string $telefono = '5551234567'): Client
    {
        return Client::create(['telefono' => $telefono, 'nivel' => 'nuevo', 'puntos' => 0, 'total_citas' => 0]);
    }

    private function makeService(int $duracionMin = 30): Service
    {
        return Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => $duracionMin, 'activo' => true]);
    }

    private function futureDate(): string
    {
        return Carbon::now()->addDays(7)->format('Y-m-d');
    }

    public function test_create_appointment_persists_with_generated_code(): void
    {
        Notification::fake();

        $barber = $this->makeBarber();
        $client = $this->makeClient();
        $service = $this->makeService();
        $date = $this->futureDate();

        $appointment = $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertNotEmpty($appointment->code);

        $fresh = Appointment::find($appointment->id);
        $this->assertNotNull($fresh);
        $this->assertSame('pendiente', $fresh->estado);
        $this->assertSame((string) $client->id, (string) $fresh->client_id);
    }

    public function test_create_appointment_throws_when_client_already_booked_same_day(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $service = $this->makeService();
        $barber1 = $this->makeBarber();
        $barber2 = $this->makeBarber();
        $date = $this->futureDate();

        $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber1->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $this->expectException(ClientAlreadyBookedException::class);

        $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber2->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '15:00:00',
            'hora_fin' => '15:30:00',
            'estado' => 'pendiente',
        ]);
    }

    public function test_create_appointment_throws_when_barber_has_overlapping_time(): void
    {
        Notification::fake();

        $barber = $this->makeBarber();
        $service = $this->makeService();
        $client1 = $this->makeClient('5550000001');
        $client2 = $this->makeClient('5550000002');
        $date = $this->futureDate();

        $this->service->createAppointment([
            'client_id' => (string) $client1->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $this->expectException(AppointmentConflictException::class);

        // Se solapa con el rango anterior (09:15-09:45 cruza 09:00-09:30).
        $this->service->createAppointment([
            'client_id' => (string) $client2->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:15:00',
            'hora_fin' => '09:45:00',
            'estado' => 'pendiente',
        ]);
    }

    public function test_create_appointment_does_not_conflict_across_different_days(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService();

        $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        // Mismo cliente, mismo barbero, mismo horario, pero un día distinto:
        // ninguna de las dos reglas de conflicto debe dispararse.
        $second = $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => Carbon::now()->addDays(8)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $this->assertInstanceOf(Appointment::class, $second);
    }

    public function test_update_appointment_can_reschedule_without_conflicting_with_itself(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService();
        $date = $this->futureDate();

        $appointment = $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        // Re-guardar exactamente el mismo horario no debe auto-generar un
        // "conflicto" contra sí misma (ignoreAppointmentId).
        $result = $this->service->updateAppointment((string) $appointment->id, [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'notas' => 'Reagendada',
        ]);

        $this->assertTrue($result);
        $this->assertSame('Reagendada', Appointment::find($appointment->id)->notas);
    }

    public function test_update_appointment_still_detects_conflict_with_a_different_appointment(): void
    {
        Notification::fake();

        $client = $this->makeClient();
        $barber = $this->makeBarber();
        $service = $this->makeService();
        $date = $this->futureDate();

        $this->service->createAppointment([
            'client_id' => (string) $this->makeClient('5550000003')->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'pendiente',
        ]);

        $toReschedule = $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $date,
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'pendiente',
        ]);

        $this->expectException(AppointmentConflictException::class);

        // Reagendar la segunda cita para que choque con la primera (de otro cliente).
        $this->service->updateAppointment((string) $toReschedule->id, [
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'fecha' => $date,
            'hora_inicio' => '09:15:00',
            'hora_fin' => '09:45:00',
        ]);
    }

    public function test_get_available_slots_excludes_booked_range_and_respects_schedule(): void
    {
        Notification::fake();

        $barber = $this->makeBarber();
        $client = $this->makeClient();
        $service = $this->makeService(30);
        $target = Carbon::now()->addDays(7);

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_working' => true,
        ]);

        $slotsBeforeBooking = $this->service->getAvailableSlots($barber, $target->format('Y-m-d'), $service);
        $this->assertCount(6, $slotsBeforeBooking); // 09:00..11:30 cada 30 min

        $this->service->createAppointment([
            'client_id' => (string) $client->id,
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'fecha' => $target->format('Y-m-d'),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:30:00',
            'estado' => 'confirmada',
        ]);

        $slotsAfterBooking = $this->service->getAvailableSlots($barber, $target->format('Y-m-d'), $service);

        $this->assertCount(5, $slotsAfterBooking);
        $this->assertNotContains('10:00', array_column($slotsAfterBooking, 'time'));
        $this->assertContains('09:00', array_column($slotsAfterBooking, 'time'));
    }

    public function test_get_available_slots_is_empty_when_barber_does_not_work_that_day(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService();
        $target = Carbon::now()->addDays(7);

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'is_working' => false,
        ]);

        $slots = $this->service->getAvailableSlots($barber, $target->format('Y-m-d'), $service);

        $this->assertSame([], $slots);
    }
}
