<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

/**
 * Integridad y búsquedas (auditoría DBA, 2026-10-09). Idempotente.
 *
 * - `gift_cards.code` y `push_subscriptions.endpoint` pasan a ser únicos: Mongo no tiene restricciones de unicidad
 *   salvo por índice, y sin él dos tarjetas pueden compartir código o un mismo navegador registrarse dos veces
 *   (doble notificación push). Antes de crearlos se verificó que no había duplicados.
 * - `chat_messages` (por usuario y fecha) y `work_images` (por trabajo) solo tenían `_id`.
 * - Se quita `clients.nivel_1`: 4 valores posibles, casi no filtra y cuesta una escritura por cliente.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        $db = DB::connection('mongodb')->getDatabase();

        $this->safe(fn () => $db->selectCollection('gift_cards')->createIndex(['code' => 1], ['name' => 'gift_cards_code_unique', 'unique' => true]));
        $this->safe(fn () => $db->selectCollection('push_subscriptions')->createIndex(['endpoint' => 1], ['name' => 'push_subscriptions_endpoint_unique', 'unique' => true]));
        $this->safe(fn () => $db->selectCollection('chat_messages')->createIndex(['user_id' => 1, 'created_at' => 1], ['name' => 'chat_user_created']));
        $this->safe(fn () => $db->selectCollection('work_images')->createIndex(['work_id' => 1], ['name' => 'work_images_work']));
        $this->safe(fn () => $db->selectCollection('clients')->dropIndex('nivel_1'), [27]);
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getDatabase();

        foreach ([['gift_cards', 'gift_cards_code_unique'], ['push_subscriptions', 'push_subscriptions_endpoint_unique'], ['chat_messages', 'chat_user_created'], ['work_images', 'work_images_work']] as [$collection, $name]) {
            $this->safe(fn () => $db->selectCollection($collection)->dropIndex($name), [27]);
        }
        $this->safe(fn () => $db->selectCollection('clients')->createIndex(['nivel' => 1], ['name' => 'nivel_1']));
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
