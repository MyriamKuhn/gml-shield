<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Restrict mission statuses to the authorized values.
     */
    public function up(): void
    {
        // PostgreSQL-specific constraint.
        // SQLite validation is covered separately by Laravel tests.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE missions
            ADD CONSTRAINT missions_status_check
            CHECK (status IN (
                'PENDING',
                'IN_PROGRESS',
                'COMPLETED',
                'CANCELLED'
            ))
        ");
    }

    /**
     * Remove the status constraint on rollback.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            ALTER TABLE missions
            DROP CONSTRAINT IF EXISTS missions_status_check
        ");
    }
};
