<?php

namespace App\Observability;

use Illuminate\Support\Facades\Log;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;

use function MongoDB\Driver\Monitoring\addSubscriber;

/**
 * Mide cada comando que el driver manda a MongoDB: acumula cuántos y cuánto tardaron en la petición actual
 * (los lee LogSlowRequests) y avisa en el log de los que pasan de OBSERVABILITY slow_query_ms. Laravel no dispara
 * QueryExecuted con este driver, por eso se engancha al monitoreo de comandos del propio driver.
 */
final class MongoCommandTimer implements CommandSubscriber
{
    public static int $commands = 0;

    public static float $millis = 0.0;

    /** @var array<string, string> requestId => "coleccion" para poder nombrarla al terminar el comando */
    private array $collections = [];

    /** Se registra una sola vez por proceso: boot() corre por cada aplicación creada (p. ej. en cada prueba). */
    public static function register(): void
    {
        static $subscriber = null;

        if ($subscriber === null) {
            $subscriber = new self;
            addSubscriber($subscriber);
        }
    }

    /** Reinicia los acumulados; el middleware lo llama al terminar cada petición. */
    public static function reset(): void
    {
        self::$commands = 0;
        self::$millis = 0.0;
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
        $name = $event->getCommandName();
        $value = ((array) $event->getCommand())[$name] ?? null;
        $this->collections[(string) $event->getRequestId()] = is_string($value) ? $value : '';
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->finish((string) $event->getRequestId(), $event->getCommandName(), $event->getDurationMicros(), 'ok');
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        $this->finish((string) $event->getRequestId(), $event->getCommandName(), $event->getDurationMicros(), 'error');
    }

    private function finish(string $requestId, string $command, int $micros, string $result): void
    {
        $collection = $this->collections[$requestId] ?? '';
        unset($this->collections[$requestId]);

        $ms = $micros / 1000;
        self::$commands++;
        self::$millis += $ms;

        $threshold = (int) config('observability.slow_query_ms', 300);
        if ($threshold >= 0 && $ms >= $threshold) {
            Log::warning('Consulta lenta a MongoDB', [
                'command' => $command,
                'collection' => $collection,
                'ms' => round($ms, 1),
                'result' => $result,
            ]);
        }
    }
}
