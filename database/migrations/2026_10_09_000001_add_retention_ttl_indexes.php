<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

/**
 * Retención de datos (auditoría DBA, 2026-10-09) con índices TTL de MongoDB: el propio servidor purga, sin cron.
 *
 * - `database_notifications`: se borran 90 días después de leerse (TTL sobre `read_at`; las no leídas no tienen
 *   `read_at` y nunca expiran).
 * - `activities` (bitácora de auditoría): se borran a los 365 días de `created_at`.
 *
 * Reemplaza el índice simple `activity_created_at` por el TTL sobre la misma clave. Idempotente.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    private const NOTIFICATIONS_READ_DAYS = 90;

    private const ACTIVITIES_DAYS = 365;

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        $this->safe(fn () => $db->selectCollection('database_notifications')->createIndex(
            ['read_at' => 1],
            ['name' => 'notif_read_at_ttl', 'expireAfterSeconds' => self::NOTIFICATIONS_READ_DAYS * 86400],
        ));

        // Una misma clave no admite un índice simple y uno TTL a la vez: se sustituye.
        $this->safe(fn () => $db->selectCollection('activities')->dropIndex('activity_created_at'), [27]);
        $this->safe(fn () => $db->selectCollection('activities')->createIndex(
            ['created_at' => 1],
            ['name' => 'activity_created_ttl', 'expireAfterSeconds' => self::ACTIVITIES_DAYS * 86400],
        ));
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        $this->safe(fn () => $db->selectCollection('database_notifications')->dropIndex('notif_read_at_ttl'), [27]);
        $this->safe(fn () => $db->selectCollection('activities')->dropIndex('activity_created_ttl'), [27]);
        $this->safe(fn () => $db->selectCollection('activities')->createIndex(['created_at' => 1], ['name' => 'activity_created_at']));
    }

    /** Tolera "índice ya existe" (85/86) y "colección/índice inexistente" (26, más los de $ignore). */
    private function safe(callable $fn, array $ignore = []): void
    {
        try {
            $fn();
        } catch (CommandException $e) {
            if (! in_array($e->getCode(), array_merge([85, 86, 26], $ignore), true)) {
                throw $e;
            }
        }
    }
};
