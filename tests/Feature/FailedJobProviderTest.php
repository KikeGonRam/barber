<?php

namespace Tests\Feature;

use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedJobProviderTest extends TestCase
{
    private ?string $failedJobUuid = null;

    protected function tearDown(): void
    {
        if ($this->failedJobUuid !== null) {
            app(FailedJobProviderInterface::class)->forget($this->failedJobUuid);
        }

        parent::tearDown();
    }

    public function test_database_uuid_provider_persists_and_recovers_failed_jobs_in_mongodb(): void
    {
        $provider = app(FailedJobProviderInterface::class);
        $this->failedJobUuid = (string) Str::uuid();
        $payload = json_encode([
            'uuid' => $this->failedJobUuid,
            'displayName' => 'Tests\\Fixtures\\FailingJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => [],
        ], JSON_THROW_ON_ERROR);

        $loggedUuid = $provider->log('redis', 'notifications', $payload, new \RuntimeException('expected test failure'));

        $this->assertSame($this->failedJobUuid, $loggedUuid);
        $stored = $provider->find($this->failedJobUuid);
        $this->assertNotNull($stored);
        $this->assertSame($this->failedJobUuid, $stored->id);
        $this->assertSame('redis', $stored->connection);
        $this->assertSame('notifications', $stored->queue);
        $this->assertStringContainsString('expected test failure', $stored->exception);
    }
}
