<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 5.4: semantic cache entries — a cached answer plus the embedding of
 * the question that produced it, so a *similar* (not just identical)
 * future question can reuse it. `scope` separates independent callers of
 * `SemanticCacheService` (currently only `ChatContextAiService`) sharing
 * this one table, the same way `agent_type` separates conversations rather
 * than one table per agent.
 *
 * Vectors stored as JSON, same portability convention already used by
 * `lexeme_embeddings`/`canonical_lexeme_embeddings`/`grammar_rule_embeddings`
 * — and, like `CandidateMatchingService`'s matching, similarity is a plain
 * linear cosine scan in PHP (`VectorMath::cosineSimilarity`), not a
 * database vector index: this table stays small (cache entries expire,
 * task 5.4's TTL) and scoped per-caller, so an ANN index would be
 * complexity without a payoff at this scale — the exact same reasoning
 * already accepted for `CandidateMatchingService`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_semantic_cache_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64);
            $table->text('question');
            $table->json('embedding');
            $table->string('model_version', 64);
            $table->text('answer');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_semantic_cache_entries');
    }
};
