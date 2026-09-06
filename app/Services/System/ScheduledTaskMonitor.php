<?php

namespace App\Services\System;

use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Cache;

/**
 * Guarda en cache la última ejecución de cada tarea programada
 * (routes/console.php), escuchando los eventos que el propio scheduler de
 * Laravel dispara en cada corrida de "schedule:run" -- sin esto no hay forma
 * de saber si una tarea programada sigue corriendo o lleva días fallando en
 * silencio hasta que alguien nota el síntoma (p. ej. recordatorios de citas
 * que dejaron de llegar).
 *
 * Nota sobre ScheduledTaskFinished vs Failed: Laravel dispara AMBOS eventos
 * cuando una tarea termina con exit code distinto de cero (Finished primero,
 * luego Failed) -- el registro final aquí queda correcto porque Failed pisa
 * el valor que Finished acababa de guardar, pero el orden importa si alguna
 * vez se reescribe este listener.
 */
class ScheduledTaskMonitor
{
    private const CACHE_PREFIX = 'schedule:last-run:';

    // Cubre incluso la tarea mensual (monthlyOn) sin que el dato desaparezca
    // del dashboard entre una corrida y la siguiente.
    private const TTL_DAYS = 35;

    public function recordFinished(ScheduledTaskFinished $event): void
    {
        $this->store($this->taskName($event->task), [
            'status' => 'success',
            'ran_at' => now()->toIso8601String(),
            'runtime_ms' => (int) round($event->runtime * 1000),
            'error' => null,
        ]);
    }

    public function recordFailed(ScheduledTaskFailed $event): void
    {
        $this->store($this->taskName($event->task), [
            'status' => 'failed',
            'ran_at' => now()->toIso8601String(),
            'runtime_ms' => null,
            'error' => $event->exception->getMessage(),
        ]);
    }

    /**
     * @return array{status: string, ran_at: string, runtime_ms: int|null, error: string|null}|null
     */
    public function lastRun(string $taskName): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$taskName);
    }

    private function taskName(Event $task): string
    {
        return $task->description ?: $task->command;
    }

    private function store(string $taskName, array $data): void
    {
        Cache::put(self::CACHE_PREFIX.$taskName, $data, now()->addDays(self::TTL_DAYS));
    }
}
