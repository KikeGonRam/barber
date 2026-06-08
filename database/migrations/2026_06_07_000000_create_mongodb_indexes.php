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
            // Skip duplicate index errors (code 85 = IndexOptionsConflict, 86 = IndexKeySpecsConflict)
            if (! in_array($e->getCode(), [85, 86])) {
                throw $e;
            }
        }
    }

    public function up(): void
    {
        $this->safeIndex('users', function (Blueprint $c) {
            $c->unique('email');
            $c->index('deleted_at');
        });

        $this->safeIndex('barbers', function (Blueprint $c) {
            $c->index('user_id');
            $c->index('activo');
        });

        $this->safeIndex('clients', function (Blueprint $c) {
            $c->index('user_id');
        });

        $this->safeIndex('appointments', function (Blueprint $c) {
            $c->index('barber_id');
            $c->index('client_id');
            $c->index('service_id');
            $c->index('fecha');
            $c->index('estado');
            $c->index('deleted_at');
        });

        $this->safeIndex('payments', function (Blueprint $c) {
            $c->index('appointment_id');
            $c->index('created_by');
        });

        $this->safeIndex('products', function (Blueprint $c) {
            $c->index('categoria');
            $c->index('deleted_at');
        });

        $this->safeIndex('inventory_movements', function (Blueprint $c) {
            $c->index('product_id');
            $c->index('appointment_id');
            $c->index('user_id');
        });

        $this->safeIndex('works', function (Blueprint $c) {
            $c->index('barbero_id');
            $c->index('work_date');
        });

        $this->safeIndex('comments', function (Blueprint $c) {
            $c->index('work_id');
            $c->index('user_id');
        });

        $this->safeIndex('reactions', function (Blueprint $c) {
            $c->index(['work_id', 'user_id']);
        });

        $this->safeIndex('saved_works', function (Blueprint $c) {
            $c->unique(['user_id', 'work_id']);
        });

        $this->safeIndex('barber_schedules', function (Blueprint $c) {
            $c->index('barber_id');
        });

        $this->safeIndex('roles', function (Blueprint $c) {
            $c->index(['name', 'guard_name']);
        });

        $this->safeIndex('permissions', function (Blueprint $c) {
            $c->index(['name', 'guard_name']);
        });

        $this->safeIndex('activity_log', function (Blueprint $c) {
            $c->index('log_name');
            $c->index('causer_id');
            $c->index('subject_id');
            $c->index('created_at');
        });

        $this->safeIndex('mobile_api_tokens', function (Blueprint $c) {
            $c->index('user_id');
            $c->unique('token_hash');
            $c->index('expires_at');
        });
    }

    public function down(): void
    {
        // MongoDB collections are dropped with their indexes; nothing needed here.
    }
};
