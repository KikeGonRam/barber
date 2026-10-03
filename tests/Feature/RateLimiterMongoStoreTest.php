<?php

namespace Tests\Feature;

use Illuminate\Cache\RateLimiter;
use Tests\TestCase;

/**
 * Con CACHE_STORE=file (staging) cada tarea de ECS lleva su propio contador de
 * throttle, así que con dos tareas el límite real se duplica. CACHE_LIMITER_STORE=mongodb
 * guarda los contadores en MongoDB: esta prueba simula dos tareas (dos instancias de
 * RateLimiter resueltas por separado) y comprueba que comparten la cuenta.
 *
 * Corre contra mongo-test (barber_db_test) vía test.ps1, como el resto de la suite.
 */
class RateLimiterMongoStoreTest extends TestCase
{
    private const KEY = 'prueba-limiter-compartido|203.0.113.7';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.limiter', 'mongodb');
        $this->limiter()->clear(self::KEY);
    }

    protected function tearDown(): void
    {
        $this->limiter()->clear(self::KEY);

        parent::tearDown();
    }

    /** Una "tarea" nueva: el RateLimiter se resuelve de cero desde la configuración. */
    private function limiter(): RateLimiter
    {
        $this->app->forgetInstance(RateLimiter::class);
        $this->app->forgetInstance('cache');

        return $this->app->make(RateLimiter::class);
    }

    public function test_dos_tareas_comparten_el_contador_de_intentos(): void
    {
        $tareaA = $this->limiter();
        $tareaB = $this->limiter();

        for ($i = 0; $i < 3; $i++) {
            $tareaA->hit(self::KEY, 60);
        }
        for ($i = 0; $i < 2; $i++) {
            $tareaB->hit(self::KEY, 60);
        }

        $this->assertSame(5, $tareaA->attempts(self::KEY));
        $this->assertTrue($tareaB->tooManyAttempts(self::KEY, 5));
    }

    public function test_sin_configurar_usa_el_store_por_defecto(): void
    {
        config()->set('cache.limiter', null);
        config()->set('cache.default', 'array');

        $limiter = $this->limiter();
        $limiter->hit(self::KEY, 60);

        // En el store por defecto (array) no hay nada en MongoDB.
        config()->set('cache.limiter', 'mongodb');
        $this->assertSame(0, $this->limiter()->attempts(self::KEY));
    }
}
