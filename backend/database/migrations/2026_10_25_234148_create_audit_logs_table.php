<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable to audit unauthenticated actions.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 255);

            // Supports IPv4 and IPv6 addresses.
            $table->ipAddress('ip_address')->nullable();

            // Preserve the field name required by Studi.
            $table->timestamp('timestamp')->useCurrent();

            $table->index('timestamp');
            $table->index(['user_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
