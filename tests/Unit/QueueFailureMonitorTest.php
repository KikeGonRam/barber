<?php

namespace Tests\Unit;

use App\Services\System\QueueFailureMonitor;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class QueueFailureMonitorTest extends TestCase
{
    public function test_it_logs_actionable_metadata_without_payload_or_exception_message(): void
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->once()->andReturn('notifications');
        $job->shouldReceive('resolveName')->once()->andReturn('App\\Jobs\\SendReceipt');
        $job->shouldReceive('getJobId')->once()->andReturn('job-123');

        Log::shouldReceive('error')
            ->once()
            ->with('Queue job failed', [
                'connection' => 'redis',
                'queue' => 'notifications',
                'job' => 'App\\Jobs\\SendReceipt',
                'job_id' => 'job-123',
                'exception' => \RuntimeException::class,
            ]);

        $exception = new \RuntimeException('cliente@example.com no pudo recibir su comprobante');

        app(QueueFailureMonitor::class)->recordFailed(new JobFailed('redis', $job, $exception));
    }
}
