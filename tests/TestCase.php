<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\RefreshMongoDatabase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SAFETY NET: the Docker container exports MONGODB_URI/MONGO_DATABASE
        // as real process environment variables (from the real .env), which
        // beat .env.testing and even <env force="true"> in phpunit.xml. That
        // silently pointed the entire test suite at the production/dev
        // database (barber_db) instead of barber_db_test, and truncated it
        // on every run. Force the database name here, in PHP, where nothing
        // can override it — then hard-abort if it still isn't a *_test db.
        config(['database.connections.mongodb.database' => 'barber_db_test']);
        \Illuminate\Support\Facades\DB::purge('mongodb');

        $activeDatabase = config('database.connections.mongodb.database');
        if (! str_ends_with($activeDatabase, '_test')) {
            throw new \RuntimeException(
                "Refusing to run tests: MongoDB connection resolved to [{$activeDatabase}], ".
                'which does not look like an isolated test database. Aborting before any '.
                'truncation could touch real data.'
            );
        }

        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
            'verified',
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        Config::set('mail.default', 'array');

        // Truncate MongoDB collections if test class opts in via RefreshMongoDatabase
        $uses = array_flip(class_uses_recursive(static::class));
        if (isset($uses[RefreshMongoDatabase::class])) {
            $this->truncateMongoCollections();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function truncateMongoCollections(): void
    {
        $db = DB::connection('mongodb')->getDatabase();

        // Roles and permissions are config data seeded once; protecting them from
        // truncation eliminates ~17 Atlas M0 writes per test setUp (850 total for
        // AdminApiTest alone), which is the root cause of read-after-write failures.
        $preserved = ['roles', 'permissions', 'model_has_permissions'];

        foreach ($db->listCollections() as $collectionInfo) {
            if (! in_array($collectionInfo->getName(), $preserved)) {
                $db->selectCollection($collectionInfo->getName())->deleteMany([]);
            }
        }
    }
}
