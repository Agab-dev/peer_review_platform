<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id('document_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->unsignedInteger('version_number');
            $table->text('pdf_file_path');
            $table->longText('html_content')->nullable()
                ->comment('Must not be read before checking html_ready = true');
            $table->boolean('html_ready')->default(false)
                ->comment('Set to true after successful PDF-to-HTML conversion');
            $table->timestamp('uploaded_at')->useCurrent();

            // No soft delete — version history is permanent (REQ-022)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
