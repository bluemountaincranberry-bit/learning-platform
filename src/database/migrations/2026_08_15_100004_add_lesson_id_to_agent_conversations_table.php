<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `agent_conversations` had no FK to any owning entity before this — the
 * `content_authoring`/`student_tutor` agents never needed one (a Content
 * created mid-chat is just referenced in the reply text, not linked back).
 * The Lesson feature needs the reverse: a Lesson's chat thread must be
 * findable from the Lesson itself, so `LessonConversationController` can
 * load "this lesson's conversation" directly instead of searching messages.
 * Nullable/nullOnDelete so the two existing agent types keep working with
 * no value here at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->foreignId('lesson_id')->nullable()->after('created_by')->constrained('lessons')->nullOnDelete();
            $table->index('lesson_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('lesson_id');
        });
    }
};
