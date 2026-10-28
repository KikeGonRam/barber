<?php

namespace Tests\OAT;

use Tests\TestCase;

/**
 * El panel de Laravel Pulse no debe delatar su existencia: quien no tiene sesión de
 * administrador o ingeniero recibe 404, igual que una ruta inexistente (y no un 403).
 */
class PulseHiddenTest extends TestCase
{
    public function test_a_guest_gets_404_on_pulse_not_403(): void
    {
        $this->get('/pulse')->assertNotFound();
    }
}
