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
        // Orders never got explicit indexes — listing pages filter/sort by
        // client_id, estado and created_at (Reception/OrderController,
        // Client/OrderController), which fell back to a full collection scan.
        $this->safeIndex('orders', function (Blueprint $c) {
            $c->index(['client_id', 'created_at']);
            $c->index(['estado', 'created_at']);
        });

        // Activity log: ActivityLogController filters by date range on
        // created_at (whereDate/whereBetween) with no supporting index.
        $this->safeIndex('activity_log', function (Blueprint $c) {
            $c->index('created_at');
        });
    }

    public function down(): void {}
};
