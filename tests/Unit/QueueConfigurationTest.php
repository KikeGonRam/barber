<?php

namespace Tests\Unit;

use Tests\TestCase;

class QueueConfigurationTest extends TestCase
{
    public function test_async_queue_connections_dispatch_only_after_database_commit(): void
    {
        foreach (['database', 'beanstalkd', 'sqs', 'redis'] as $connection) {
            $this->assertTrue(
                config("queue.connections.{$connection}.after_commit"),
                "The {$connection} queue may dispatch work before its database transaction commits.",
            );
        }
    }
}
