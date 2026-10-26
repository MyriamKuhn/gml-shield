<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add a unique identifier for audit event deduplication.
     *
     * Existing audit records remain unchanged.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('event_id')
                ->nullable()
                ->unique();
        });
    }

    /**
     * Remove the additional event identifier.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropUnique(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
