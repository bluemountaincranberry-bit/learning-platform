<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills content_lexemes.lexeme_id from the (about to be dropped)
     * content_lexeme_links table, which is being folded into a single column
     * on the occurrence table it always described.
     *
     * Two source shapes exist in content_lexeme_links, handled separately:
     *
     * - `content_lexeme_id` set: the common path (plain tokenizer + auto-sync,
     *   or an AI candidate with no match) — a content_lexemes row already
     *   exists, just needs its lexeme_id filled in. Written as a correlated
     *   subquery (no `UPDATE ... FROM`) so it runs on both Postgres (app/dev)
     *   and SQLite (test suite, see phpunit.xml DB_CONNECTION=sqlite).
     *
     * - `content_lexeme_id` null: AiCandidateApplyService's "matched an
     *   existing lexeme" branch deliberately skipped creating an occurrence
     *   row (to avoid double-syncing a duplicate canonical lexeme) — so no
     *   content_lexemes row exists for these at all yet. One has to be
     *   synthesized, reconstructing type/text/frequency from the sibling
     *   content_lexeme_candidates row when available (same content's AI run,
     *   matched to the same lexeme), falling back to the lexeme's own lemma.
     *   Processed in chunks so a large table isn't locked by one giant
     *   transaction.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE content_lexemes
            SET lexeme_id = (
                SELECT content_lexeme_links.lexeme_id
                FROM content_lexeme_links
                WHERE content_lexeme_links.content_lexeme_id = content_lexemes.id
            )
            WHERE content_lexemes.lexeme_id IS NULL
              AND EXISTS (
                  SELECT 1 FROM content_lexeme_links
                  WHERE content_lexeme_links.content_lexeme_id = content_lexemes.id
              )
        SQL);

        DB::table('content_lexeme_links')
            ->whereNull('content_lexeme_id')
            ->orderBy('id')
            ->chunkById(500, function ($links): void {
                foreach ($links as $link) {
                    $candidate = DB::table('content_lexeme_candidates')
                        ->join('ai_analysis_runs', 'ai_analysis_runs.id', '=', 'content_lexeme_candidates.ai_analysis_run_id')
                        ->where('ai_analysis_runs.content_id', $link->content_id)
                        ->where('content_lexeme_candidates.matched_lexeme_id', $link->lexeme_id)
                        ->orderByDesc('content_lexeme_candidates.id')
                        ->first(['content_lexeme_candidates.text', 'content_lexeme_candidates.type', 'content_lexeme_candidates.frequency']);

                    $lexeme = DB::table('lexemes')->where('id', $link->lexeme_id)->first(['lemma']);

                    DB::table('content_lexemes')->insert([
                        'content_id' => $link->content_id,
                        'lexeme_id' => $link->lexeme_id,
                        'type' => $candidate !== null && $candidate->type !== 'word' ? 'phrase' : 'word',
                        'text' => $candidate->text ?? $lexeme->lemma ?? '',
                        'sort_order' => 0,
                        'frequency' => $candidate->frequency ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * Data migration — only undoes the column backfill, not the synthesized
     * occurrence rows created above (those would need a marker to identify
     * safely, which isn't worth adding for a one-off backfill).
     */
    public function down(): void
    {
        DB::table('content_lexemes')->update(['lexeme_id' => null]);
    }
};
