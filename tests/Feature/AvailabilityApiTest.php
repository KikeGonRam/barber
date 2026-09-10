<?php

namespace Tests\Feature;

use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\Service;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas. GET availability/slots
 * (App\Http\Controllers\Api\Appointment\AvailabilityController) es el
 * endpoint que calcula horarios libres para el flujo de reserva -- usado
 * directo por el modal de "Nueva cita" del cliente y por el formulario de
 * citas de staff en frontend-urban. El cálculo en sí (AppointmentService::
 * getAvailableSlots()) ya tenía cobertura en AppointmentServiceIntegrationTest;
 * este archivo cubre el contrato HTTP que faltaba: que la ruta es pública
 * (sin token), la validación de parámetros, y la forma de la respuesta --
 * incluida la ruta legacy sin prefijo /v1 que se mantiene por compatibilidad
 * retro con cachés de frontend viejas.
 */
class AvailabilityApiTest extends TestCase
{
    protected function tearDown(): void
    {
        BarberSchedule::query()->delete();
        Barber::query()->delete();
        Service::query()->delete();

        parent::tearDown();
    }

    private function futureDate(): Carbon
    {
        return Carbon::now()->addDays(7);
    }

    public function test_slots_endpoint_is_public_and_returns_the_expected_shape(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero disponibilidad', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $target = $this->futureDate();

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'is_working' => true,
        ]);

        // Sin header Authorization: el catálogo/disponibilidad es público,
        // igual que /services y /barbers.
        $response = $this->getJson('/api/v1/availability/slots?'.http_build_query([
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'date' => $target->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['slots']);
        $response->assertJsonCount(4, 'slots'); // 09:00, 09:30, 10:00, 10:30 (duración 30 min, cada 30 min)
    }

    public function test_slots_requires_barber_id_service_id_and_date(): void
    {
        $response = $this->getJson('/api/v1/availability/slots');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barber_id', 'service_id', 'date']);
    }

    public function test_slots_rejects_a_date_in_the_past(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero disponibilidad', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);

        $response = $this->getJson('/api/v1/availability/slots?'.http_build_query([
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'date' => Carbon::yesterday()->format('Y-m-d'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['date']);
    }

    public function test_slots_rejects_an_unknown_barber_or_service(): void
    {
        $response = $this->getJson('/api/v1/availability/slots?'.http_build_query([
            'barber_id' => '000000000000000000000000',
            'service_id' => '000000000000000000000000',
            'date' => $this->futureDate()->format('Y-m-d'),
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['barber_id', 'service_id']);
    }

    public function test_slots_is_empty_when_the_barber_does_not_work_that_day(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero disponibilidad', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $target = $this->futureDate();

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'is_working' => false,
        ]);

        $response = $this->getJson('/api/v1/availability/slots?'.http_build_query([
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'date' => $target->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertExactJson(['slots' => []]);
    }

    /**
     * Regresión: routes/api.php mantiene GET api/availability/slots (sin
     * prefijo /v1) "for older frontend cache" -- si algo la rompe sin que
     * nadie lo note, esa caché vieja empezaría a fallar en silencio.
     */
    public function test_legacy_route_without_v1_prefix_still_works(): void
    {
        $barber = Barber::create(['nombre' => 'Barbero disponibilidad', 'activo' => true]);
        $service = Service::create(['nombre' => 'Corte clásico', 'precio' => 200, 'duracion_min' => 30, 'activo' => true]);
        $target = $this->futureDate();

        BarberSchedule::create([
            'barber_id' => (string) $barber->id,
            'day_of_week' => $target->dayOfWeek,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_working' => true,
        ]);

        $response = $this->getJson('/api/availability/slots?'.http_build_query([
            'barber_id' => (string) $barber->id,
            'service_id' => (string) $service->id,
            'date' => $target->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertJsonCount(2, 'slots'); // 09:00, 09:30
    }
}
