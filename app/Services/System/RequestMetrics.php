<?php

namespace App\Services\System;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Métricas de tráfico de la API por minuto (auditoría UI/UX I-01): peticiones, errores 5xx y latencia media.
 *
 * Cada petición muestreada suma a tres contadores de su minuto en la caché de Laravel (`ub:metrics:{AAAAMMDD-HHMM}:*`,
 * 26 h de vida). No guarda rutas, usuarios ni cuerpos: solo cuántas, cuántas fallaron y cuánto tardaron. Con una
 * fracción de muestreo menor a 1 las cifras absolutas se reescalan para estimar el total.
 */
class RequestMetrics
{
    private const TTL_SECONDS = 93600;

    private const PREFIX = 'ub:metrics:';

    private const DRIVERS_ATOMICOS = ['redis', 'memcached', 'array'];

    /**
     * Solo con un almacén de contadores atómicos (Redis o Memcached). Los almacenes basados en MongoDB o en archivos
     * no sirven: el `increment` de MongoDB deja documentos sin `key` y rompe el índice único de la colección `cache`
     * (incidente en staging del 29-oct-2026), así que ahí las métricas quedan apagadas aunque estén activadas.
     */
    public function enabled(): bool
    {
        if (! config('observability.metrics_enabled', false)) {
            return false;
        }

        $store = config('observability.metrics_cache_store') ?: config('cache.default');

        return in_array(config("cache.stores.{$store}.driver"), self::DRIVERS_ATOMICOS, true);
    }

    public function sampleRate(): float
    {
        return max(0.01, min(1.0, (float) config('observability.metrics_sample_rate', 0.2)));
    }

    private function store(): Repository
    {
        $store = config('observability.metrics_cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    public function bucketKey(CarbonInterface $at): string
    {
        return self::PREFIX.Carbon::instance($at)->format('Ymd-Hi');
    }

    /** Suma una petición al minuto indicado. */
    public function record(int $status, float $milliseconds, ?CarbonInterface $at = null): void
    {
        $key = $this->bucketKey($at ?? Carbon::now());
        $store = $this->store();

        $this->bump($store, $key.':n', 1);
        $this->bump($store, $key.':ms', (int) round($milliseconds));
        if ($status >= 500) {
            $this->bump($store, $key.':e', 1);
        }
    }

    private function bump(Repository $store, string $key, int $by): void
    {
        $store->add($key, 0, self::TTL_SECONDS);
        $store->increment($key, $by);
    }

    /**
     * Serie de los últimos `$minutes` minutos agrupada en cubos de `$step` minutos (el más reciente al final).
     *
     * @return array{step: int, sample_rate: float, points: list<array{t: string, requests: int, errors: int, avg_ms: float|null}>}
     */
    public function series(int $minutes, int $step = 1, ?CarbonInterface $now = null): array
    {
        $minutes = max(5, min(1440, $minutes));
        $step = max(1, min(60, $step));
        $now = Carbon::instance($now ?? Carbon::now())->startOfMinute();
        $rate = $this->sampleRate();
        $store = $this->store();

        $claves = [];
        for ($i = $minutes - 1; $i >= 0; $i--) {
            $base = $this->bucketKey($now->copy()->subMinutes($i));
            array_push($claves, $base.':n', $base.':e', $base.':ms');
        }
        $valores = $store->many($claves);

        $puntos = [];
        for ($i = $minutes - 1; $i >= 0; $i -= $step) {
            $n = $e = $ms = 0;
            for ($j = 0; $j < $step && ($i - $j) >= 0; $j++) {
                $base = $this->bucketKey($now->copy()->subMinutes($i - $j));
                $n += (int) ($valores[$base.':n'] ?? 0);
                $e += (int) ($valores[$base.':e'] ?? 0);
                $ms += (int) ($valores[$base.':ms'] ?? 0);
            }
            $puntos[] = [
                't' => $now->copy()->subMinutes($i)->toIso8601String(),
                'requests' => (int) round($n / $rate),
                'errors' => (int) round($e / $rate),
                'avg_ms' => $n > 0 ? round($ms / $n, 1) : null,
            ];
        }

        return ['step' => $step, 'sample_rate' => $rate, 'points' => $puntos];
    }
}
