<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editor_assignments', function (Blueprint $table) {
            $table->id('assignment_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->foreignId('editor_id')->constrained('users', 'user_id');
            $table->boolean('is_primary')->default(false)
                ->comment('true = primary decision-making editor; false = co-editor (advisory only). Never mutated — rows are soft-deleted and replaced.');
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable()
                ->comment('Soft delete — set when editor is replaced (REQ-037)');
        });

        // Partial unique index — only one active (non-deleted) assignment per editor per submission
        // Standard unique() cannot be used here because soft-deleted rows would block reassignment
        DB::statement(
            'CREATE UNIQUE INDEX editor_assignments_active_unique
             ON editor_assignments (research_id, editor_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('editor_assignments');
    }
};
