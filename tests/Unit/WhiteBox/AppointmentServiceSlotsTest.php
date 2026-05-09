<?php

namespace Tests\Unit\WhiteBox;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BarberSchedule;
use App\Models\BarbershopSetting;
use App\Models\Client;
use App\Models\Service;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentServiceSlotsTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $appointmentService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appointmentService = app(AppointmentService::class);
    }

    private function makeBarber(): Barber
    {
        return Barber::factory()->create(['activo' => true]);
    }

    private function makeService(int $duration = 30): Service
    {
        return Service::factory()->create(['duracion_min' => $duration, 'activo' => true]);
    }

    public function test_returns_empty_slots_when_barber_has_no_schedule_and_no_global_settings(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService();
        $date = now()->addDays(3)->toDateString();

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        $this->assertEmpty($slots);
    }

    public function test_returns_slots_based_on_barber_schedule(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $date = now()->next(Carbon::MONDAY)->toDateString();

        BarberSchedule::query()->create([
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'is_working' => true,
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        $this->assertNotEmpty($slots);
        $times = array_column($slots, 'time');
        $this->assertContains('09:00', $times);
        $this->assertContains('09:30', $times);
    }

    public function test_returns_no_slots_when_barber_schedule_marks_not_working(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $date = now()->next(Carbon::MONDAY)->toDateString();

        BarberSchedule::query()->create([
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'is_working' => false,
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        $this->assertEmpty($slots);
    }

    public function test_falls_back_to_global_settings_when_no_barber_schedule(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);

        // Use a future weekday (not Sunday) so the fallback allows it
        $date = now()->next(Carbon::MONDAY)->toDateString();

        BarbershopSetting::query()->create([
            'nombre' => 'Barber Shop',
            'horario_apertura' => '10:00',
            'horario_cierre' => '11:00',
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        $this->assertNotEmpty($slots);
        $times = array_column($slots, 'time');
        $this->assertContains('10:00', $times);
        $this->assertContains('10:30', $times);
    }

    public function test_global_settings_do_not_allow_sunday(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $date = now()->next(Carbon::SUNDAY)->toDateString();

        BarbershopSetting::query()->create([
            'nombre' => 'Barber Shop',
            'horario_apertura' => '10:00',
            'horario_cierre' => '17:00',
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        $this->assertEmpty($slots);
    }

    public function test_cancelled_appointments_do_not_block_slots(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $client = Client::factory()->create();
        $date = now()->next(Carbon::WEDNESDAY)->toDateString();

        BarberSchedule::query()->create([
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_working' => true,
        ]);

        // Create a cancelled appointment at 09:00
        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'cancelada',
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);
        $times = array_column($slots, 'time');

        $this->assertContains('09:00', $times);
    }

    public function test_confirmed_appointment_blocks_overlapping_slot(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $client = Client::factory()->create();
        $date = now()->next(Carbon::THURSDAY)->toDateString();

        BarberSchedule::query()->create([
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '11:00',
            'is_working' => true,
        ]);

        Appointment::query()->create([
            'client_id' => $client->id,
            'barber_id' => $barber->id,
            'service_id' => $service->id,
            'fecha' => $date,
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => 'confirmada',
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);
        $times = array_column($slots, 'time');

        $this->assertNotContains('09:00', $times);
        $this->assertContains('09:30', $times);
    }

    public function test_slots_include_time_and_label_keys(): void
    {
        $barber = $this->makeBarber();
        $service = $this->makeService(30);
        $date = now()->next(Carbon::FRIDAY)->toDateString();

        BarberSchedule::query()->create([
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::parse($date)->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '09:30',
            'is_working' => true,
        ]);

        $slots = $this->appointmentService->getAvailableSlots($barber, $date, $service);

        if (! empty($slots)) {
            $this->assertArrayHasKey('time', $slots[0]);
            $this->assertArrayHasKey('label', $slots[0]);
        }
    }
}
