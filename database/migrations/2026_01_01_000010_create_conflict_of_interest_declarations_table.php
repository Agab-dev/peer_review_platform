<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conflict_of_interest_declarations', function (Blueprint $table) {
            $table->id('coi_id');
            $table->foreignId('research_id')->constrained('research_submissions', 'research_id');
            $table->foreignId('declared_by')->constrained('users', 'user_id')
                ->comment('Can be a reviewer or a handling editor (REQ-049)');
            $table->text('description');
            $table->timestamp('declared_at')->useCurrent();

            // No soft delete — permanent audit record (REQ-047, REQ-048, REQ-049)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conflict_of_interest_declarations');
    }
};
