<?php

namespace Tests\SIT;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La caché vive en MongoDB (MongoStore): contadores de límites de peticiones, ventanas del guardián de abuso,
 * catálogo cacheado y candados. Sin índice TTL sobre `expires_at` los documentos vencidos no se borraban nunca y se
 * acumulaban en Atlas M0 (hallazgo de la medición de carga HT-20). Ver la migración add_cache_ttl_indexes.
 */
class CacheTtlIndexTest extends TestCase
{
    public function test_la_migracion_crea_un_indice_ttl_inmediato_en_cache_y_cache_locks(): void
    {
        $migration = require database_path('migrations/2026_11_23_000000_add_cache_ttl_indexes.php');
        $migration->up();
        // Repetirla no falla: createIndex con las mismas opciones es idempotente.
        $migration->up();

        $db = DB::connection('mongodb')->getMongoDB();

        foreach (['cache', 'cache_locks'] as $collection) {
            $ttl = null;
            foreach ($db->selectCollection($collection)->listIndexes() as $index) {
                if ($index->getName() === 'expires_at_ttl') {
                    $ttl = $index;
                }
            }

            $this->assertNotNull($ttl, "Falta el índice TTL en {$collection}.");
            $this->assertSame(['expires_at' => 1], $ttl->getKey());
            $this->assertTrue($ttl->isTtl());
            $this->assertSame(0, $ttl['expireAfterSeconds']);
        }
    }
}
