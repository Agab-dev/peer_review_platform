<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annotations', function (Blueprint $table) {
            $table->id('annotation_id');
            $table->foreignId('document_id')->constrained('document_versions', 'document_id');
            $table->foreignId('reviewer_id')->constrained('users', 'user_id');
            $table->unsignedInteger('text_range_start');
            $table->unsignedInteger('text_range_end');
            $table->text('comment');
            $table->timestamp('created_at')->useCurrent();

            // Prevents duplicate annotation by same reviewer on same text range (REQ-074)
            $table->unique(
                ['document_id', 'reviewer_id', 'text_range_start', 'text_range_end'],
                'annotations_unique_range'
            );

            // No soft delete — deleting would break linked forum_discussions integrity (REQ-068)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annotations');
    }
};
