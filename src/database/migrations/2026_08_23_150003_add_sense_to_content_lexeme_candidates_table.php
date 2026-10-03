<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            // Task 10.3: a short gloss distinguishing this meaning from other
            // meanings of the same lemma (e.g. "move quickly on foot" vs.
            // "manage/operate"), used only to resolve-or-create a LexemeSense
            // at Apply time — not itself a display field.
            $table->string('sense')->nullable()->after('part_of_speech');
        });
    }

    public function down(): void
    {
        Schema::table('content_lexeme_candidates', function (Blueprint $table): void {
            $table->dropColumn('sense');
        });
    }
};
