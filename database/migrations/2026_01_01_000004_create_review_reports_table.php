<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id('report_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->foreignId('reviewer_id')->constrained('users', 'user_id');
            $table->text('summary');
            $table->text('major_issues');
            $table->text('minor_issues')->nullable();
            $table->string('recommendation', 30)
                ->comment('Enum: accept | revisions_required | reject. Reviewer opinion only — does not change research status.');
            $table->timestamp('submitted_at')->useCurrent();

            // No soft delete — permanent academic record (REQ-052)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');
    }
};
