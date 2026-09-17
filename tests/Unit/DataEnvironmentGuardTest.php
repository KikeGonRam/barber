<?php

namespace Tests\Unit;

use App\Support\DataEnvironmentGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DataEnvironmentGuardTest extends TestCase
{
    #[DataProvider('safeConfigurations')]
    public function test_it_accepts_isolated_configurations(
        string $appEnvironment,
        string $dataEnvironment,
        string $uri,
        string $database,
    ): void {
        DataEnvironmentGuard::assertSafe($appEnvironment, $dataEnvironment, $uri, $database);

        $this->addToAssertionCount(1);
    }

    public static function safeConfigurations(): array
    {
        return [
            'shared Atlas' => ['local', 'shared', 'mongodb+srv://masked@example.mongodb.net/', 'barber_db'],
            'development replica set' => ['local', 'development', 'mongodb://mongo-dev:27017/?replicaSet=rsdev', 'urbanblade_dev'],
            'Docker tests' => ['testing', 'testing', 'mongodb://mongo-test:27017/?replicaSet=rs0', 'barber_db_test'],
            'CI tests' => ['testing', 'testing', 'mongodb://127.0.0.1:27017/?replicaSet=rs0', 'barber_db_test'],
        ];
    }

    #[DataProvider('unsafeConfigurations')]
    public function test_it_rejects_mixed_configurations(
        string $appEnvironment,
        string $dataEnvironment,
        string $uri,
        string $database,
    ): void {
        $this->expectException(RuntimeException::class);

        DataEnvironmentGuard::assertSafe($appEnvironment, $dataEnvironment, $uri, $database);
    }

    public static function unsafeConfigurations(): array
    {
        return [
            'development on Atlas' => ['local', 'development', 'mongodb+srv://masked@example.mongodb.net/', 'urbanblade_dev'],
            'development with shared database' => ['local', 'development', 'mongodb://mongo-dev:27017/?replicaSet=rsdev', 'barber_db'],
            'tests on Atlas' => ['testing', 'testing', 'mongodb+srv://masked@example.mongodb.net/', 'barber_db_test'],
            'tests with development database' => ['testing', 'testing', 'mongodb://mongo-test:27017/?replicaSet=rs0', 'urbanblade_dev'],
            'testing app marked shared' => ['testing', 'shared', 'mongodb+srv://masked@example.mongodb.net/', 'barber_db'],
            'shared context on mongo-dev' => ['local', 'shared', 'mongodb://mongo-dev:27017/?replicaSet=rsdev', 'barber_db'],
            'unknown context' => ['local', 'production', 'mongodb+srv://masked@example.mongodb.net/', 'barber_db'],
        ];
    }
}
