<?php

namespace Tests\Unit;

use App\Services\Appointment\AppointmentStatusService;
use PHPUnit\Framework\TestCase;

class AppointmentStatusServiceTest extends TestCase
{
    private AppointmentStatusService $service;

    protected function setUp(): void
    {
        $this->service = new AppointmentStatusService;
    }

    public function test_can_transition_follows_the_documented_state_machine(): void
    {
        $this->assertTrue($this->service->canTransition('pendiente', 'confirmada'));
        $this->assertTrue($this->service->canTransition('pendiente', 'cancelada'));
        $this->assertFalse($this->service->canTransition('pendiente', 'completada'));
        $this->assertFalse($this->service->canTransition('pendiente', 'en_proceso'));
        $this->assertFalse($this->service->canTransition('pendiente', 'no_asistio'));

        $this->assertTrue($this->service->canTransition('confirmada', 'en_proceso'));
        $this->assertTrue($this->service->canTransition('confirmada', 'completada'));
        $this->assertTrue($this->service->canTransition('confirmada', 'no_asistio'));
        $this->assertTrue($this->service->canTransition('confirmada', 'cancelada'));
        $this->assertFalse($this->service->canTransition('confirmada', 'pendiente'));

        $this->assertTrue($this->service->canTransition('en_proceso', 'completada'));
        $this->assertTrue($this->service->canTransition('en_proceso', 'cancelada'));
        $this->assertFalse($this->service->canTransition('en_proceso', 'no_asistio'));
        $this->assertFalse($this->service->canTransition('en_proceso', 'pendiente'));
    }

    public function test_terminal_states_allow_no_further_transitions(): void
    {
        foreach (['completada', 'cancelada', 'no_asistio'] as $terminal) {
            $this->assertSame([], $this->service->allowedFor($terminal));
            $this->assertFalse($this->service->canTransition($terminal, 'confirmada'));
            $this->assertFalse($this->service->canTransition($terminal, 'pendiente'));
        }
    }

    public function test_can_transition_rejects_an_unknown_origin_state(): void
    {
        $this->assertFalse($this->service->canTransition('estado_inventado', 'confirmada'));
    }

    public function test_allowed_for_matches_the_documented_transitions(): void
    {
        $this->assertSame(['confirmada', 'cancelada'], $this->service->allowedFor('pendiente'));
        $this->assertSame(['en_proceso', 'completada', 'no_asistio', 'cancelada'], $this->service->allowedFor('confirmada'));
        $this->assertSame(['completada', 'cancelada'], $this->service->allowedFor('en_proceso'));
    }

    public function test_role_can_set_matches_the_role_map(): void
    {
        // cancelada es la unica transicion que el cliente puede disparar.
        $this->assertTrue($this->service->roleCanSet('cancelada', 'cliente'));
        $this->assertFalse($this->service->roleCanSet('confirmada', 'cliente'));
        $this->assertFalse($this->service->roleCanSet('completada', 'cliente'));
        $this->assertFalse($this->service->roleCanSet('no_asistio', 'cliente'));

        foreach (['confirmada', 'en_proceso', 'completada', 'no_asistio', 'cancelada'] as $to) {
            $this->assertTrue($this->service->roleCanSet($to, 'barbero'));
            $this->assertTrue($this->service->roleCanSet($to, 'recepcionista'));
            $this->assertTrue($this->service->roleCanSet($to, 'administrador'));
        }
    }

    public function test_role_can_set_rejects_null_or_unknown_role(): void
    {
        $this->assertFalse($this->service->roleCanSet('confirmada', null));
        $this->assertFalse($this->service->roleCanSet('confirmada', 'rol_inventado'));
    }

    public function test_is_chargeable_matches_the_documented_states(): void
    {
        $this->assertFalse($this->service->isChargeable('pendiente'));
        $this->assertTrue($this->service->isChargeable('confirmada'));
        $this->assertTrue($this->service->isChargeable('en_proceso'));
        $this->assertTrue($this->service->isChargeable('completada'));
        $this->assertFalse($this->service->isChargeable('cancelada'));
        $this->assertFalse($this->service->isChargeable('no_asistio'));
    }
}
