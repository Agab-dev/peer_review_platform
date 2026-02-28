<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id('reply_id');
            $table->foreignId('discussion_id')->constrained('forum_discussions', 'discussion_id');
            $table->foreignId('user_id')->constrained('users', 'user_id');
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('deleted_at')->nullable()
                ->comment('Soft delete — reply hidden when non-null, thread structure preserved');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_replies');
    }
};
