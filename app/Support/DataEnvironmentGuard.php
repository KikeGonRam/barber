<?php

namespace App\Support;

use RuntimeException;

final class DataEnvironmentGuard
{
    /**
     * Impide que desarrollo/pruebas terminen en Atlas o que la aplicación
     * compartida use por accidente los Mongo locales.
     */
    public static function assertSafe(
        string $appEnvironment,
        string $dataEnvironment,
        string $uri,
        string $database,
    ): void {
        // Una excepcion de esta guarda puede ocurrir antes de que Laravel
        // configure el manejador. PHP CLI incluia los argumentos del metodo
        // (y por tanto la URI con credenciales) en la traza: se omiten antes
        // de cualquier validacion o throw.
        ini_set('zend.exception_ignore_args', '1');

        $appEnvironment = strtolower(trim($appEnvironment));
        $dataEnvironment = strtolower(trim($dataEnvironment));
        $database = trim($database);
        $normalizedUri = strtolower(trim($uri));

        if (! in_array($dataEnvironment, ['shared', 'development', 'testing'], true)) {
            throw new RuntimeException('DATA_ENVIRONMENT debe ser shared, development o testing.');
        }

        $usesAtlas = str_starts_with($normalizedUri, 'mongodb+srv://');
        $usesDevHost = self::containsHost($normalizedUri, 'mongo-dev');
        $usesTestHost = self::containsHost($normalizedUri, 'mongo-test')
            || self::containsHost($normalizedUri, '127.0.0.1')
            || self::containsHost($normalizedUri, 'localhost');

        if ($usesAtlas && in_array($database, ['urbanblade_dev', 'barber_db_test'], true)) {
            throw new RuntimeException('Conexion insegura: una base local no puede usar MongoDB Atlas.');
        }

        if ($appEnvironment === 'testing' && $dataEnvironment !== 'testing') {
            throw new RuntimeException('APP_ENV=testing requiere DATA_ENVIRONMENT=testing.');
        }

        match ($dataEnvironment) {
            'development' => self::assertDevelopment($usesAtlas, $usesDevHost, $database),
            'testing' => self::assertTesting($usesAtlas, $usesTestHost, $database),
            'shared' => self::assertShared($usesDevHost, $usesTestHost, $database),
        };
    }

    private static function assertDevelopment(bool $usesAtlas, bool $usesDevHost, string $database): void
    {
        if ($usesAtlas || ! $usesDevHost || $database !== 'urbanblade_dev') {
            throw new RuntimeException(
                'DATA_ENVIRONMENT=development requiere mongo-dev y la base urbanblade_dev.'
            );
        }
    }

    private static function assertTesting(bool $usesAtlas, bool $usesTestHost, string $database): void
    {
        if ($usesAtlas || ! $usesTestHost || $database !== 'barber_db_test') {
            throw new RuntimeException(
                'DATA_ENVIRONMENT=testing requiere un Mongo local y la base barber_db_test.'
            );
        }
    }

    private static function assertShared(bool $usesDevHost, bool $usesTestHost, string $database): void
    {
        if ($usesDevHost || $usesTestHost || in_array($database, ['urbanblade_dev', 'barber_db_test'], true)) {
            throw new RuntimeException(
                'DATA_ENVIRONMENT=shared no puede usar hosts ni bases reservados para desarrollo/pruebas.'
            );
        }
    }

    private static function containsHost(string $uri, string $host): bool
    {
        return preg_match('/(?:@|\/\/|,|^)'.preg_quote($host, '/').'(?=[:\/?,]|$)/', $uri) === 1;
    }
}
