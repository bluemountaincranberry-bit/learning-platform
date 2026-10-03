<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Task 10.3: a distinct meaning of a lemma (e.g. "run" = move on foot
        // vs. "run" = manage a business), so translations/examples can be
        // scoped to the meaning they actually belong to instead of all
        // hanging off the lemma undifferentiated. `normalized_gloss` backs
        // cheap exact-string dedup within one lexeme (AiCandidateApplyService
        // resolves-or-creates a sense this way — no embedding infrastructure
        // needed at this scale, see the roadmap for why).
        Schema::create('lexeme_senses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('lexemes')->cascadeOnDelete();
            $table->string('part_of_speech', 32)->nullable();
            $table->string('gloss');
            $table->string('normalized_gloss')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['lexeme_id', 'normalized_gloss']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexeme_senses');
    }
};
