<?php

namespace Tests\OAT;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * Con varias tareas de ECS cada una corre schedule:work. Todas las tareas programadas
 * deben declararse con onOneServer() para que, con SCHEDULE_CACHE_STORE apuntando a un
 * almacén compartido, cada ejecución ocurra una sola vez. Sin la variable el mutex usa
 * la caché por defecto y el comportamiento no cambia.
 */
class ScheduleOnOneServerTest extends TestCase
{
    public function test_todas_las_tareas_programadas_usan_one_server(): void
    {
        $eventos = $this->app->make(Schedule::class)->events();

        $this->assertNotEmpty($eventos);

        foreach ($eventos as $evento) {
            $this->assertTrue(
                $evento->onOneServer,
                'La tarea programada no usa onOneServer(): '.($evento->description ?? $evento->command)
            );
        }
    }

    public function test_el_almacen_del_mutex_es_opcional(): void
    {
        $this->assertNull(config('cache.schedule_store'));
    }
}
