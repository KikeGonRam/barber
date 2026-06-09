<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * Drop-in replacement for RefreshDatabase compatible with MongoDB Atlas.
 *
 * RefreshDatabase uses multi-document transactions to isolate tests. MongoDB Atlas
 * wraps writes in the transaction session but rollback is unreliable with the
 * laravel-mongodb driver when multiple factories (LogsActivity, Spatie Permission)
 * create documents across different sessions.
 *
 * This trait truncates all collections before each test instead, giving each
 * test a clean slate without relying on transaction rollback.
 */
trait RefreshMongoDatabase
{
    protected function refreshDatabase(): void
    {
        $this->truncateMongoCollections();
    }

    protected function truncateMongoCollections(): void
    {
        $db = DB::connection('mongodb')->getMongoDB();

        foreach ($db->listCollections() as $collectionInfo) {
            $db->selectCollection($collectionInfo->getName())->deleteMany([]);
        }
    }
}
