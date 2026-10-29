<?php

namespace Tests\UT;

use App\Services\System\RequestMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RequestMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'observability.metrics_cache_store' => 'array', 'observability.metrics_sample_rate' => 1.0]);
        Cache::store('array')->flush();
    }

    public function test_cuenta_peticiones_errores_y_latencia_media_por_minuto(): void
    {
        $now = Carbon::parse('2026-10-29 12:30:10');
        $metrics = new RequestMetrics;

        $metrics->record(200, 100, $now);
        $metrics->record(200, 300, $now);
        $metrics->record(500, 200, $now);

        $serie = $metrics->series(5, 1, $now);

        $this->assertCount(5, $serie['points']);
        $ultimo = $serie['points'][4];
        $this->assertSame(3, $ultimo['requests']);
        $this->assertSame(1, $ultimo['errors']);
        $this->assertSame(200.0, $ultimo['avg_ms']);
        $this->assertNull($serie['points'][0]['avg_ms']);
    }

    public function test_agrupa_en_cubos_y_reescala_por_el_muestreo(): void
    {
        config(['observability.metrics_sample_rate' => 0.5]);
        $now = Carbon::parse('2026-10-29 12:30:00');
        $metrics = new RequestMetrics;

        $metrics->record(200, 50, $now->copy()->subMinute());
        $metrics->record(200, 150, $now);

        $serie = $metrics->series(10, 5, $now);

        $this->assertSame(5, $serie['step']);
        $this->assertCount(2, $serie['points']);
        $this->assertSame(4, $serie['points'][1]['requests']);
        $this->assertSame(100.0, $serie['points'][1]['avg_ms']);
    }

    public function test_limita_minutos_y_paso_a_rangos_validos(): void
    {
        $serie = (new RequestMetrics)->series(99999, 1, Carbon::parse('2026-10-29 12:00:00'));

        $this->assertCount(1440, $serie['points']);
    }

    public function test_solo_se_activa_con_un_almacen_de_contadores_atomicos(): void
    {
        $metrics = new RequestMetrics;

        config(['observability.metrics_enabled' => false]);
        $this->assertFalse($metrics->enabled());

        config(['observability.metrics_enabled' => true, 'observability.metrics_cache_store' => 'array']);
        $this->assertTrue($metrics->enabled());

        config(['cache.stores.mongodb' => ['driver' => 'mongodb'], 'observability.metrics_cache_store' => 'mongodb']);
        $this->assertFalse($metrics->enabled());

        config(['cache.stores.file' => ['driver' => 'file'], 'observability.metrics_cache_store' => null, 'cache.default' => 'file']);
        $this->assertFalse($metrics->enabled());
    }
}
