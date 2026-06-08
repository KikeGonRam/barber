<?php

use Illuminate\Database\Migrations\Migration;
use MongoDB\Laravel\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('users', function (Blueprint $collection) {
            $collection->unique('email');
            $collection->index('deleted_at');
        });

        Schema::connection('mongodb')->table('barbers', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('activo');
        });

        Schema::connection('mongodb')->table('clients', function (Blueprint $collection) {
            $collection->index('user_id');
        });

        Schema::connection('mongodb')->table('appointments', function (Blueprint $collection) {
            $collection->index('barber_id');
            $collection->index('client_id');
            $collection->index('service_id');
            $collection->index('fecha');
            $collection->index('estado');
            $collection->index('deleted_at');
        });

        Schema::connection('mongodb')->table('payments', function (Blueprint $collection) {
            $collection->index('appointment_id');
            $collection->index('created_by');
        });

        Schema::connection('mongodb')->table('products', function (Blueprint $collection) {
            $collection->index('categoria');
            $collection->index('deleted_at');
        });

        Schema::connection('mongodb')->table('inventory_movements', function (Blueprint $collection) {
            $collection->index('product_id');
            $collection->index('appointment_id');
            $collection->index('user_id');
        });

        Schema::connection('mongodb')->table('works', function (Blueprint $collection) {
            $collection->index('barbero_id');
            $collection->index('work_date');
        });

        Schema::connection('mongodb')->table('comments', function (Blueprint $collection) {
            $collection->index('work_id');
            $collection->index('user_id');
        });

        Schema::connection('mongodb')->table('reactions', function (Blueprint $collection) {
            $collection->index(['work_id', 'user_id']);
        });

        Schema::connection('mongodb')->table('saved_works', function (Blueprint $collection) {
            $collection->index(['user_id', 'work_id']);
        });

        Schema::connection('mongodb')->table('barber_schedules', function (Blueprint $collection) {
            $collection->index('barber_id');
        });

        Schema::connection('mongodb')->table('roles', function (Blueprint $collection) {
            $collection->index(['name', 'guard_name']);
        });

        Schema::connection('mongodb')->table('permissions', function (Blueprint $collection) {
            $collection->index(['name', 'guard_name']);
        });

        Schema::connection('mongodb')->table('activity_log', function (Blueprint $collection) {
            $collection->index('log_name');
            $collection->index('causer_id');
            $collection->index('subject_id');
            $collection->index('created_at');
        });

        Schema::connection('mongodb')->table('mobile_api_tokens', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->unique('token_hash');
            $collection->index('expires_at');
        });
    }

    public function down(): void
    {
        // MongoDB collections are dropped with their indexes; nothing needed here.
    }
};
