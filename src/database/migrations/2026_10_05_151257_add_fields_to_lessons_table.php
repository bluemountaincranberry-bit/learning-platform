<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Date of the lesson (distinct from created_at/updated_at)
            $table->date('lesson_date')->nullable()->after('language');

            // Teacher / group name (replaces 'tutor' conceptually; we keep tutor for compat)
            $table->string('teacher')->nullable()->after('lesson_date');

            // Lesson topic
            $table->string('topic')->nullable()->after('teacher');

            // Free-form tags array
            $table->json('tags')->nullable()->after('topic');

            // Human-editable notes (Markdown), separate from source_text (AI transcript)
            $table->text('notes')->nullable()->after('tags');

            // Homework field
            $table->text('homework')->nullable()->after('notes');

            // Index for filtering by date
            $table->index(['user_id', 'lesson_date']);
        });

        // Backfill existing lessons (separate step after schema change)
        \DB::table('lessons')->chunkById(100, function ($lessons) {
            foreach ($lessons as $lesson) {
                $date = $lesson->created_at instanceof \DateTimeInterface
                    ? $lesson->created_at->format('Y-m-d')
                    : (is_string($lesson->created_at) ? substr($lesson->created_at, 0, 10) : null);
                \DB::table('lessons')->where('id', $lesson->id)->update([
                    'lesson_date' => $date,
                    'teacher' => $lesson->tutor,
                    'notes' => '',
                    'homework' => '',
                    'tags' => json_encode([]),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'lesson_date']);
            $table->dropColumn(['lesson_date', 'teacher', 'topic', 'tags', 'notes', 'homework']);
        });
    }
};
