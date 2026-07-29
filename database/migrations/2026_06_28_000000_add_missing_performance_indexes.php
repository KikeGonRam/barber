<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    private function safeIndex(string $collection, Closure $callback): void
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
        // Notifications: queried by notifiable_id + read_at on every page load (nav badge)
        $this->safeIndex('notifications', function (Blueprint $c) {
            $c->index(['notifiable_id', 'read_at']);
            $c->index('created_at');
        });

        // Clients: filtered by created_at for "new clients today" KPI
        $this->safeIndex('clients', function (Blueprint $c) {
            $c->index('created_at');
            $c->index('nivel');
        });

        // Appointments: estado compound indexes for dashboard client/barber queries
        // Covers: where client_id + estado + fecha (dashboard visit chart, stats counts)
        $this->safeIndex('appointments', function (Blueprint $c) {
            $c->index(['client_id', 'estado', 'fecha']);
            $c->index(['barber_id', 'estado', 'fecha']);
            $c->index(['estado', 'fecha']);
        });

        // Payments: queried by appointment_id in invoice list
        $this->safeIndex('payments', function (Blueprint $c) {
            $c->index(['appointment_id', 'created_at']);
        });

        // Loyalty transactions: queried by client_id + latest
        $this->safeIndex('loyalty_transactions', function (Blueprint $c) {
            $c->index(['client_id', 'created_at']);
        });

        // Raffle results: queried by client_id + latest
        $this->safeIndex('raffle_results', function (Blueprint $c) {
            $c->index(['client_id', 'created_at']);
        });
    }

    public function down(): void {}
};
