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

        // Roles and permissions are config data — preserve them so the seeder
        // doesn't re-insert 17 documents per test (850 total for AdminApiTest),
        // which floods Atlas M0 and widens secondary-replica lag windows.
        $preserved = ['roles', 'permissions', 'model_has_permissions'];

        foreach ($db->listCollections() as $collectionInfo) {
            if (! in_array($collectionInfo->getName(), $preserved)) {
                $db->selectCollection($collectionInfo->getName())->deleteMany([]);
            }
        }
    }
}
