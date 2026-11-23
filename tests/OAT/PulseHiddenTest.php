<?php

namespace Tests\OAT;

use Tests\TestCase;

/**
 * El panel de Laravel Pulse no debe delatar su existencia: quien no tiene sesión de
 * administrador recibe 404, igual que una ruta inexistente (y no un 403). El rol
 * "ingeniero" se quitó el 23-nov-2026 (auditoría de funciones); el Gate viewPulse ya
 * no lo reconoce (ver AppServiceProvider::boot()).
 */
class PulseHiddenTest extends TestCase
{
    public function test_a_guest_gets_404_on_pulse_not_403(): void
    {
        $this->get('/pulse')->assertNotFound();
    }
}
