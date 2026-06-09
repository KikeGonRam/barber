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
        foreach ($db->listCollections() as $collectionInfo) {
            $db->selectCollection($collectionInfo->getName())->deleteMany([]);
        }
    }
}
