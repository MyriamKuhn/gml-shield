<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add encrypted warehouse access codes to missions.
     *
     * Nullable for compatibility with existing missions.
     * Encryption is handled by the application layer.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->text('secret_code')->nullable();
        });
    }

    /**
     * Remove the encrypted secret code column.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('secret_code');
        });
    }
};
