<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Salvaguarda contra el incidente del 2026-08-28: si bootstrap/cache/
     * config.php queda cacheado con los valores de Atlas (p. ej. porque
     * .docker/entrypoint.sh corrió "php artisan optimize" al reiniciar el
     * contenedor), Laravel deja de leer variables de entorno en absoluto —
     * el --env-file de test.ps1 queda sin efecto de forma silenciosa, y la
     * suite completa corre contra la Atlas compartida con spark/. Cada
     * tearDown() de las Feature tests borra datos reales sin ningún error
     * visible. Esto ya pasó una vez y destruyó Users/Appointments/Clients/
     * Barbers/Payments/etc. en producción. En vez de confiar en que quien
     * corra los tests recuerde limpiar la cache primero, se verifica en
     * caliente en cada test: si la base resuelta no es la de pruebas,
     * aborta ANTES de que cualquier tearDown() pueda tocar un solo
     * documento real.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // La conexión analítica también se valida: AnalyticsApiTest borra
        // AnalyticsInsight en tearDown() y, sin ANALYTICS_* en .env.testing,
        // heredaba la URI de Atlas (usuario de solo lectura) del contenedor.
        foreach (['mongodb', 'mongodb_analytics'] as $connection) {
            $database = config("database.connections.{$connection}.database");
            $dsn = (string) config("database.connections.{$connection}.dsn");

            if ($database !== 'barber_db_test' || str_contains($dsn, 'mongodb.net') || str_contains($dsn, 'mongodb+srv')) {
                // NO usar $this->fail(): PHPUnit igual ejecuta el tearDown() de
                // cada test, y esos tearDown() borran colecciones enteras contra
                // la base resuelta (2026-09-18: así se borraron services, barbers,
                // clients, payments y appointments de la Atlas real). Hay que
                // matar el proceso antes de que llegue cualquier tearDown().
                fwrite(STDERR,
                    "\nSEGURO: la conexión {$connection} resolvió a la base '{$database}' (no 'barber_db_test') ".
                    "o a un host de Atlas.\n".
                    "Esto casi seguro significa que bootstrap/cache/config.php está cacheado con los\n".
                    "valores de Atlas (ver .docker/entrypoint.sh) y --env-file .env.testing no tuvo\n".
                    "efecto. Usa .\\test.ps1 (nunca 'php artisan test' directo).\n".
                    "Proceso abortado antes de ejecutar ningún tearDown().\n"
                );
                exit(1);
            }
        }
    }
}
