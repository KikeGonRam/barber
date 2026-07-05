<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mongodb';

    private function safeIndex(string $collection, \Closure $callback): void
    {
        try {
            Schema::connection('mongodb')->table($collection, $callback);
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function up(): void
    {
        // Compound indexes for appointments.
        // Most dashboard queries filter on (barber_id + fecha) or (client_id + fecha + estado).
        // Single-field indexes on barber_id, fecha, estado exist but MongoDB can only use one
        // per query stage; compound indexes let it satisfy multi-field filters in a single scan.
        $this->safeIndex('appointments', function (Blueprint $c) {
            $c->index(['barber_id', 'fecha']);
            $c->index(['client_id', 'fecha']);
            $c->index(['barber_id', 'fecha', 'estado']);
            $c->index(['client_id', 'fecha', 'estado']);
        });

        // Payments need created_at for weekly income range queries.
        $this->safeIndex('payments', function (Blueprint $c) {
            $c->index('created_at');
        });

        // Chatbot telemetry trend queries always filter on all three fields together.
        $this->safeIndex('activity_log', function (Blueprint $c) {
            $c->index(['log_name', 'description', 'created_at']);
        });
    }

    public function down(): void {}
};
