<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable middleware for easier testing
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            'verified',
        ]);

        if (Schema::hasTable('permissions')) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        }
    }
}
