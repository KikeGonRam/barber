<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MongoDB\Driver\Exception\CommandException;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Optimización de colecciones (auditoría DBA, 2026-10-09). Idempotente: se puede repetir sin efecto.
 *
 * 1. Los modelos Activity y DatabaseNotification apuntan a `activities` y `database_notifications` (antes
 *    declaraban $collection, que laravel-mongodb v5 ignora). Esas colecciones solo tenían `_id`: se indexan.
 * 2. Se quitan índices que son prefijo de otro compuesto (no aportan lecturas y encarecen cada escritura).
 * 3. Se eliminan `activity_log`, `notifications` e `inventories`, que ningún modelo usa; solo si están vacías,
 *    así que nunca se pierde un documento.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    /** Índices redundantes: cada uno es prefijo de otro compuesto que ya existe. */
    private const REDUNDANT = [
        'appointments' => [
            'barber_id_1', 'client_id_1', 'estado_1', 'barber_id_1_fecha_1', 'client_id_1_fecha_1',
            'barber_id_1_fecha_1_hora_inicio_1',
        ],
        'payments' => ['appointment_id_1'],
        'mobile_api_tokens' => ['user_id_1'],
    ];

    private const UNUSED_COLLECTIONS = ['activity_log', 'notifications', 'inventories'];

    public function up(): void
    {
        $this->safe(fn () => Schema::connection('mongodb')->table('database_notifications', function (Blueprint $c) {
            $c->index(['notifiable_id', 'read_at', 'created_at'], 'notif_notifiable_read_created');
            $c->index(['created_at'], 'notif_created_at');
        }));

        $this->safe(fn () => Schema::connection('mongodb')->table('activities', function (Blueprint $c) {
            $c->index(['subject_type', 'subject_id', 'created_at'], 'activity_subject_created');
            $c->index(['causer_id', 'created_at'], 'activity_causer_created');
            $c->index(['log_name', 'created_at'], 'activity_log_name_created');
            $c->index(['created_at'], 'activity_created_at');
        }));

        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::REDUNDANT as $collection => $names) {
            foreach ($names as $name) {
                $this->safe(fn () => $db->selectCollection($collection)->dropIndex($name), [27]);
            }
        }

        foreach (self::UNUSED_COLLECTIONS as $collection) {
            if ($db->selectCollection($collection)->countDocuments([]) === 0) {
                $db->selectCollection($collection)->drop();
            }
        }
    }

    public function down(): void
    {
        // Revertir solo repone los índices quitados; las colecciones vacías las recrea el framework si hacen falta.
        $this->safe(fn () => Schema::connection('mongodb')->table('appointments', function (Blueprint $c) {
            $c->index(['barber_id'], 'barber_id_1');
            $c->index(['client_id'], 'client_id_1');
            $c->index(['estado'], 'estado_1');
            $c->index(['barber_id', 'fecha'], 'barber_id_1_fecha_1');
            $c->index(['client_id', 'fecha'], 'client_id_1_fecha_1');
            $c->index(['barber_id', 'fecha', 'hora_inicio'], 'barber_id_1_fecha_1_hora_inicio_1');
        }));
        $this->safe(fn () => Schema::connection('mongodb')->table('payments', fn (Blueprint $c) => $c->index(['appointment_id'], 'appointment_id_1')));
        $this->safe(fn () => Schema::connection('mongodb')->table('mobile_api_tokens', fn (Blueprint $c) => $c->index(['user_id'], 'user_id_1')));

        $this->safe(fn () => Schema::connection('mongodb')->table('database_notifications', function (Blueprint $c) {
            $c->dropIndex('notif_notifiable_read_created');
            $c->dropIndex('notif_created_at');
        }), [27]);
        $this->safe(fn () => Schema::connection('mongodb')->table('activities', function (Blueprint $c) {
            $c->dropIndex('activity_subject_created');
            $c->dropIndex('activity_causer_created');
            $c->dropIndex('activity_log_name_created');
            $c->dropIndex('activity_created_at');
        }), [27]);
    }

    /** Ejecuta tolerando "índice ya existe" (85/86) y "colección/índice inexistente" (26, más los de $ignore). */
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
