<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfills user_lexeme_progress.lexeme_id from content_lexemes.lexeme_id
     * (itself just backfilled by the previous migration). "Learned" is moving
     * from being tracked per-occurrence to being tracked per-canonical-lexeme,
     * so a user who separately marked the same word learned via two different
     * occurrences (e.g. the same word appearing in two different videos) now
     * collapses to one row — keeping the earliest learned_at, since that's
     * the moment they actually learned the word.
     */
    public function up(): void
    {
        // Subquery-style UPDATE/DELETE below (no `UPDATE ... FROM`, no `DISTINCT ON`)
        // so this runs on both Postgres (app/dev) and SQLite (test suite, see
        // phpunit.xml DB_CONNECTION=sqlite) without a driver branch.
        DB::statement(<<<'SQL'
            UPDATE user_lexeme_progress
            SET lexeme_id = (
                SELECT content_lexemes.lexeme_id
                FROM content_lexemes
                WHERE content_lexemes.id = user_lexeme_progress.content_lexeme_id
            )
            WHERE user_lexeme_progress.lexeme_id IS NULL
              AND EXISTS (
                  SELECT 1 FROM content_lexemes
                  WHERE content_lexemes.id = user_lexeme_progress.content_lexeme_id
                    AND content_lexemes.lexeme_id IS NOT NULL
              )
        SQL);

        DB::statement(<<<'SQL'
            DELETE FROM user_lexeme_progress
            WHERE lexeme_id IS NOT NULL
              AND id NOT IN (
                  SELECT id FROM (
                      SELECT id,
                             ROW_NUMBER() OVER (PARTITION BY user_id, lexeme_id ORDER BY learned_at ASC, id ASC) AS rn
                      FROM user_lexeme_progress
                      WHERE lexeme_id IS NOT NULL
                  ) ranked
                  WHERE rn = 1
              )
        SQL);
    }

    public function down(): void
    {
        DB::table('user_lexeme_progress')->update(['lexeme_id' => null]);
    }
};
