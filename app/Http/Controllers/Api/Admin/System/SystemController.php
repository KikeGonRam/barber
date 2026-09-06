<?php

namespace App\Http\Controllers\Api\Admin\System;

use App\Services\System\ScheduledTaskMonitor;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use MongoDB\Laravel\Connection as MongoConnection;

/**
 * Dashboard de estado del servidor para el rol "ingeniero" (y administrador):
 * conectividad de MongoDB/Redis, tamaño de la cola y contador de jobs
 * fallidos, y última ejecución de cada tarea programada. Es deliberadamente
 * de solo lectura -- no expone ninguna acción de gestión, solo diagnóstico.
 * Métricas más finas (requests lentos, excepciones, consultas lentas) viven
 * en el dashboard nativo de Laravel Pulse (/pulse, mismo Gate::('viewPulse')
 * que este controlador respeta indirectamente vía el middleware de la ruta).
 */
class SystemController
{
    public function __construct(private readonly ScheduledTaskMonitor $taskMonitor) {}

    public function status(): JsonResponse
    {
        return response()->json([
            'app' => $this->appInfo(),
            'database' => $this->mongoStatus(),
            'redis' => $this->redisStatus(),
            'queue' => $this->queueStatus(),
            'scheduled_tasks' => $this->scheduledTasksStatus(),
        ]);
    }

    private function appInfo(): array
    {
        return [
            'name' => config('app.name'),
            'env' => config('app.env'),
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
        ];
    }

    /**
     * Ping a MongoDB Atlas midiendo latencia real, no solo si la conexión
     * "existe" -- una conexión configurada pero con la Atlas M0 dormida o
     * inalcanzable no debe reportarse como "up".
     */
    private function mongoStatus(): array
    {
        $start = microtime(true);

        try {
            /** @var MongoConnection $connection */
            $connection = DB::connection('mongodb');
            $connection->getMongoDB()->command(['ping' => 1]);

            return [
                'status' => 'up',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'latency_ms' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function redisStatus(): array
    {
        $start = microtime(true);

        try {
            Redis::connection()->ping();

            return [
                'status' => 'up',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'latency_ms' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Tamaño de la cola por defecto y total de jobs fallidos históricos.
     * No usa app('queue.failer')->count() porque esa clase no expone un
     * método count() propio -- ->all() ya trae todo, count() sobre el
     * resultado es lo único disponible sin tocar la tabla/colección directo.
     */
    private function queueStatus(): array
    {
        try {
            $pending = Queue::size();
        } catch (\Throwable $e) {
            $pending = null;
        }

        try {
            $failed = count(app('queue.failer')->all());
        } catch (\Throwable $e) {
            $failed = null;
        }

        return [
            'connection' => config('queue.default'),
            'pending' => $pending,
            'failed' => $failed,
        ];
    }

    /**
     * Última corrida (éxito/fallo, cuándo, cuánto tardó) de cada tarea
     * registrada en routes/console.php, alimentada por ScheduledTaskMonitor.
     * Una tarea sin corridas registradas aún sale con status "unknown" --
     * o nunca ha corrido (recién agregada) o el worker "scheduler" lleva
     * caído desde antes de que este monitor existiera.
     */
    private function scheduledTasksStatus(): array
    {
        $schedule = app(Schedule::class);

        // routes/console.php solo se carga automáticamente en contexto de
        // consola (bootstrap/app.php lo registra como el archivo de
        // "commands", no de rutas web) -- en una petición HTTP normal el
        // Schedule del contenedor existe pero llega vacío. Cargarlo aquí
        // a mano es seguro: cada request de php-fpm arranca un Application
        // nuevo, así que no hay riesgo de registrar los eventos dos veces.
        if ($schedule->events() === []) {
            require_once base_path('routes/console.php');
        }

        return collect($schedule->events())
            ->map(function ($event) {
                $name = $event->description ?: $event->command;
                $lastRun = $this->taskMonitor->lastRun($name);

                return [
                    'name' => $name,
                    'expression' => $event->expression,
                    'status' => $lastRun['status'] ?? 'unknown',
                    'ran_at' => $lastRun['ran_at'] ?? null,
                    'runtime_ms' => $lastRun['runtime_ms'] ?? null,
                    'error' => $lastRun['error'] ?? null,
                ];
            })
            ->values()
            ->all();
    }
}
