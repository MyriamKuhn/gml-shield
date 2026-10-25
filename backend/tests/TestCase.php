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
     */
    protected function setUp(): void
    {
        // Create the Laravel application without executing
        // the database-refresh traits yet.
        if (!$this->app) {
            $this->refreshApplication();
        }

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

        parent::setUp();
    }
}
