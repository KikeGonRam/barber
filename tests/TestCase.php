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

        $database = config('database.connections.mongodb.database');

        if ($database !== 'barber_db_test') {
            $this->fail(
                "SEGURO: la conexión mongodb resolvió a la base '{$database}', no 'barber_db_test'.\n".
                "Esto casi seguro significa que bootstrap/cache/config.php está cacheado con los\n".
                "valores de Atlas (ver .docker/entrypoint.sh) y --env-file .env.testing no tuvo\n".
                "efecto. Corre 'docker exec barber-app php artisan config:clear' y vuelve a intentar.\n".
                'NO ignores ni ajustes este check para que pase: su propósito es exactamente '.
                'impedir que los tearDown() de estas pruebas borren datos reales.'
            );
        }
    }
}
