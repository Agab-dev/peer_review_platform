<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_discussions', function (Blueprint $table) {
            $table->id('discussion_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->string('discussion_type', 20)
                ->comment('Enum: review_report | annotation');
            $table->foreignId('referenced_annotation_id')
                ->nullable()
                ->constrained('annotations', 'annotation_id')
                ->comment('Non-null when discussion_type = annotation');
            $table->foreignId('referenced_report_id')
                ->nullable()
                ->constrained('review_reports', 'report_id')
                ->comment('Non-null when discussion_type = review_report');
            $table->string('title', 500)->nullable()
                ->comment('First 7 words of highlighted text + ellipsis (REQ-071)');
            $table->timestamp('created_at')->useCurrent();
            $table->foreignId('created_by')->constrained('users', 'user_id');

            // No soft delete — system-created threads
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_discussions');
    }
};
