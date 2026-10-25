<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Create the documents table.
     *
     * Delivery proofs belong to a mission and must be
     * stored outside the publicly accessible directory.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();

            // Each document belongs to an existing mission.
            $table->foreignId('mission_id')
                ->constrained('missions')
                ->restrictOnDelete();

            // Relative path to the privately stored file.
            // Never store the file contents directly in this column.
            $table->string('file_path', 500);

            // MIME type detected and validated by the backend.
            $table->string('mime_type', 100);

            // Explicit upload timestamp required by Studi.
            $table->timestampTz('uploaded_at');

            // Standard Laravel timestamps for traceability.
            $table->timestampsTz();
        });
    }

    /**
     * Drop the documents table.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
