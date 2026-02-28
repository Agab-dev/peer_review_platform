<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_submissions', function (Blueprint $table) {
            $table->id('research_id');
            $table->foreignId('author_id')->constrained('users', 'user_id');
            $table->string('title', 500);
            $table->string('research_field')->nullable();
            $table->string('status', 20)->default('pending')
                ->comment('Enum: pending | accepted | rejected. Remains pending throughout review lifecycle.');
            $table->string('review_phase', 20)->nullable()
                ->comment('Enum: independent | interactive. NULL on initial submission.');
            $table->string('anonymization_model', 20)->nullable()
                ->comment('Enum: single | double | open');
            $table->date('deadline')->nullable()
                ->comment('Independent phase deadline — manually set by primary editor');
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable()
                ->comment('Set only when final Accept decision is made during Interactive Phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_submissions');
    }
};
