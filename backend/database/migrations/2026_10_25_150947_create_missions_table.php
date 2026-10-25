<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Create the missions table.
     */
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();

            // Unique business reference for each transport mission.
            $table->string('reference', 50)->unique();

            // General mission information.
            $table->string('title', 150);
            $table->text('description')->nullable();

            // Transport locations.
            $table->string('pickup_address', 500);
            $table->string('delivery_address', 500);

            // Planned mission date and time.
            $table->timestampTz('scheduled_at');

            // Mission lifecycle.
            $table->string('status', 20)->default('PENDING');

            // Assigned driver. A mission may initially be unassigned.
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            // User who created the mission.
            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestampsTz();

            // Improve queries for driver assignments and mission lists.
            $table->index(['driver_id', 'status']);
            $table->index('scheduled_at');
        });
    }

    /**
     * Drop the missions table.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
