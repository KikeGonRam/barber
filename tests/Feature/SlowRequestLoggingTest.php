<?php

namespace Tests\Feature;

use App\Observability\MongoCommandTimer;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Observabilidad: una petición o consulta a MongoDB que pasa de su umbral deja una advertencia en el log, con la
 * ruta y la colección, para saber qué frena a la API. Con el umbral en -1 no escribe nada.
 */
class SlowRequestLoggingTest extends TestCase
{
    public function test_una_peticion_lenta_deja_ruta_estado_y_tiempo_en_mongo(): void
    {
        config(['observability.slow_request_ms' => 0, 'observability.slow_query_ms' => -1]);
        $log = Log::spy();

        $this->getJson('/api/v1/services?filtro=secreto')->assertOk();

        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context = []) => $message === 'Petición lenta'
                && $context['route'] === 'api/v1/services'
                && $context['method'] === 'GET'
                && $context['status'] === 200
                && $context['mongo_commands'] >= 1
                && ! str_contains(json_encode($context), 'secreto'),
        )->once();
    }

    public function test_una_consulta_lenta_nombra_el_comando_y_la_coleccion(): void
    {
        config(['observability.slow_request_ms' => -1, 'observability.slow_query_ms' => 0]);
        $log = Log::spy();

        $this->getJson('/api/v1/services')->assertOk();

        $log->shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context = []) => $message === 'Consulta lenta a MongoDB'
                && $context['command'] !== ''
                && $context['collection'] !== ''
                && $context['result'] === 'ok',
        )->atLeast()->once();
    }

    public function test_con_el_umbral_apagado_no_escribe_avisos(): void
    {
        config(['observability.slow_request_ms' => -1, 'observability.slow_query_ms' => -1]);
        $log = Log::spy();

        $this->getJson('/api/v1/services')->assertOk();

        $log->shouldNotHaveReceived('warning', [\Mockery::pattern('/lenta/'), \Mockery::any()]);
    }

    public function test_los_acumulados_se_reinician_entre_peticiones(): void
    {
        config(['observability.slow_request_ms' => -1, 'observability.slow_query_ms' => -1]);

        $this->getJson('/api/v1/services')->assertOk();

        $this->assertSame(0, MongoCommandTimer::$commands, 'terminate() deja los contadores en cero para la siguiente petición');
    }
}
