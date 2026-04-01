<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable middleware for easier testing
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
            'verified',
        ]);

        $sqliteDatabase = Config::get('database.connections.sqlite.database');

        if (is_string($sqliteDatabase) && $sqliteDatabase !== ':memory:') {
            $sqliteDirectory = dirname($sqliteDatabase);

            if (! is_dir($sqliteDirectory)) {
                @mkdir($sqliteDirectory, 0755, true);
            }

            if (! file_exists($sqliteDatabase)) {
                @touch($sqliteDatabase);
            }
        }

        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->seed(RolePermissionSeeder::class);
        }
    }
}
