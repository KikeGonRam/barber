<?php

namespace Tests\Feature;

use App\Exceptions\Domain\InvalidAppointmentTransitionException;
use App\Models\Appointment;
use App\Services\Appointment\AppointmentStatusService;
use Tests\TestCase;

/**
 * Integración real contra el Mongo local de pruebas (ver .env.testing /
 * docker-compose.yml "mongo-test"). Cubre transition(), la única parte de
 * AppointmentStatusService con efectos secundarios (persistencia + los
 * timestamps de auditoría que dispara cada destino). La matriz de la
 * máquina de estados en sí (canTransition/allowedFor/roleCanSet/
 * isChargeable) está cubierta en tests/Unit/AppointmentStatusServiceTest
 * (lógica pura, sin DB).
 */
class AppointmentStatusServiceIntegrationTest extends TestCase
{
    private AppointmentStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AppointmentStatusService;
    }

    protected function tearDown(): void
    {
        Appointment::query()->delete();

        parent::tearDown();
    }

    private function makeAppointment(string $estado): Appointment
    {
        return Appointment::create([
            'fecha' => now()->addDays(3)->format('Y-m-d'),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '09:30:00',
            'estado' => $estado,
        ]);
    }

    public function test_transition_updates_estado_on_a_valid_move(): void
    {
        $appointment = $this->makeAppointment('pendiente');

        $this->service->transition($appointment, 'confirmada');

        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_transition_throws_and_does_not_persist_on_an_invalid_move(): void
    {
        $appointment = $this->makeAppointment('pendiente');

        $this->expectException(InvalidAppointmentTransitionException::class);

        try {
            $this->service->transition($appointment, 'completada');
        } finally {
            $this->assertSame('pendiente', Appointment::find($appointment->id)->estado);
        }
    }

    public function test_transition_to_the_same_state_is_a_silent_no_op(): void
    {
        $appointment = $this->makeAppointment('confirmada');

        $this->service->transition($appointment, 'confirmada');

        $this->assertSame('confirmada', Appointment::find($appointment->id)->estado);
    }

    public function test_transition_to_cancelada_stamps_cancelada_en(): void
    {
        $appointment = $this->makeAppointment('confirmada');

        $this->service->transition($appointment, 'cancelada');

        $fresh = Appointment::find($appointment->id);
        $this->assertSame('cancelada', $fresh->estado);
        $this->assertNotNull($fresh->cancelada_en);
    }

    public function test_transition_to_en_proceso_stamps_servicio_iniciado_en(): void
    {
        $appointment = $this->makeAppointment('confirmada');

        $this->service->transition($appointment, 'en_proceso');

        $fresh = Appointment::find($appointment->id);
        $this->assertSame('en_proceso', $fresh->estado);
        $this->assertNotNull($fresh->servicio_iniciado_en);
    }

    public function test_transition_from_a_terminal_state_always_throws(): void
    {
        $appointment = $this->makeAppointment('completada');

        $this->expectException(InvalidAppointmentTransitionException::class);

        $this->service->transition($appointment, 'confirmada');
    }
}
