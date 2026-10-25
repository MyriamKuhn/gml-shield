<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Verify the PostgreSQL test database before RefreshDatabase
     * or any other test setup can modify database contents.
     *
     * Also configure an ephemeral encryption key for tests.
     */
    protected function setUp(): void
    {
        // Create the Laravel application without executing
        // the database-refresh traits yet.
        if (!$this->app) {
            $this->refreshApplication();
        }

        // Never allow automated tests to modify
        // a PostgreSQL database other than gml_shield_test.
        if (config('database.default') === 'pgsql') {
            $environment = app()->environment();

            $database = DB::selectOne(
                'SELECT current_database() AS name'
            )?->name;

            if (
                $environment !== 'testing'
                || $database !== 'gml_shield_test'
            ) {
                throw new RuntimeException(
                    'SECURITY: PostgreSQL tests must use gml_shield_test.'
                );
            }
        }

        // Run Laravel's standard test initialization.
        parent::setUp();

        // Generate a fresh 256-bit test encryption key.
        // The key exists only in memory and is never committed.
        config()->set(
            'security.secret_code_key',
            base64_encode(random_bytes(32))
        );
    }
}
