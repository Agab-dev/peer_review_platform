<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->foreignId('reviewer_id')->constrained('users', 'user_id');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable()
                ->comment('Soft delete — set on revocation (REQ-040)');
        });

        // Partial unique index — only one active assignment per reviewer per submission
        DB::statement(
            'CREATE UNIQUE INDEX reviewer_assignments_active_unique
             ON reviewer_assignments (research_id, reviewer_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewer_assignments');
    }
};
