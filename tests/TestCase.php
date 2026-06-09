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
        $db = DB::connection('mongodb')->getMongoDB();

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
