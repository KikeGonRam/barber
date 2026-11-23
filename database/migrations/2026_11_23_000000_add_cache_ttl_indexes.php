<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\CommandException;

/**
 * Índices TTL para la caché en MongoDB (HT-20, medición de carga del 23-nov).
 *
 * La migración original de `cache`/`cache_locks` es la de Laravel para SQL: indexa `expiration`, pero MongoStore y
 * MongoLock guardan la caducidad en `expires_at` (fecha BSON). Sin índice TTL los documentos vencidos nunca se
 * borraban: cada contador del límite de peticiones y cada ventana del guardián de abuso se quedaba para siempre en
 * Atlas M0 (512 MB y recursos compartidos). Con expireAfterSeconds = 0 MongoDB los purga en cuanto vencen.
 *
 * up() no tolera conflictos a propósito: si ya existiera un índice `expires_at` sin TTL, ignorar el error dejaría la
 * caché sin purga sin que nadie se enterara. Un índice idéntico no da error (createIndex es idempotente).
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    private const COLLECTIONS = ['cache', 'cache_locks'];

    private const INDEX = 'expires_at_ttl';

    public function up(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::COLLECTIONS as $collection) {
            $db->selectCollection($collection)->createIndex(
                ['expires_at' => 1],
                ['name' => self::INDEX, 'expireAfterSeconds' => 0],
            );
        }
    }

    public function down(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach (self::COLLECTIONS as $collection) {
            try {
                $db->selectCollection($collection)->dropIndex(self::INDEX);
            } catch (CommandException $e) {
                // 26 = la colección no existe, 27 = el índice no existe: no hay nada que revertir.
                if (! in_array($e->getCode(), [26, 27], true)) {
                    throw $e;
                }
            }
        }
    }
};
