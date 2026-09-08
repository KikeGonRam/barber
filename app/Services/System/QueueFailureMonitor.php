<?php

namespace App\Services\System;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

/**
 * Registra fallos definitivos de cola sin incluir payloads, destinatarios,
 * mensajes ni trazas que puedan contener datos personales.
 *
 * Laravel conserva el job completo en failed_jobs para su recuperacion. Este
 * registro reducido existe para que la alerta operativa sea visible en logs
 * y Sentry sin duplicar informacion sensible.
 */
class QueueFailureMonitor
{
    public function recordFailed(JobFailed $event): void
    {
        Log::error('Queue job failed', [
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'job' => $event->job->resolveName(),
            'job_id' => $event->job->getJobId(),
            'exception' => $event->exception::class,
        ]);
    }
}
