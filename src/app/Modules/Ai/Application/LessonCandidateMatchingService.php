<?php

namespace App\Modules\Ai\Application;

use App\Modules\Ai\Domain\Models\Lesson;
use App\Modules\Ai\Domain\Models\LessonAnalysisRun;
use App\Modules\Ai\Domain\Models\LessonGrammarCandidate;
use App\Modules\Ai\Domain\Models\LessonLexemeCandidate;
use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;

/**
 * Resolves LessonAnalysisRun candidates against the canonical shared
 * catalog, reusing CandidateMatchingService's generic single-item matchers
 * (findBestLexemeMatch/findBestGrammarMatch) instead of its Content-coupled
 * matchRun() — a Lesson has no `content_id`/`AiAnalysisRun` to hand it.
 *
 * The two candidate kinds are resolved asymmetrically, and deliberately so:
 *
 * - Grammar match -> auto-linked into the user's own `UserGrammarRule` via
 *   GrammarProgressService::startLearning(), a plain insert with no
 *   content-occurrence coupling. Safe: this only ever writes to the user's
 *   own progress row, never to the shared `grammar_rules` table itself
 *   (the human-in-the-loop rule for the published catalog is about that
 *   table, not a user's personal "learning" flag).
 *
 * - Lexeme match -> intentionally NOT auto-linked. `user_lexeme_progress`
 *   still requires a NOT NULL `content_lexeme_id` (an occurrence inside a
 *   `Content`) and `SrsCard` scheduling is likewise joined through
 *   `content_lexemes`/`content_id` — there is no schema path today to add
 *   a bare canonical `lexeme_id` to either without the wider `lexeme_id`
 *   migration `docs/product/word-training-module-idea.md` (section 4.3)
 *   already calls out as separate future work. Until that lands, a matched
 *   lexeme candidate just carries `matched_lexeme_id`/`status=matched` so
 *   the UI can show "already in the dictionary" with a link to it.
 *
 * No candidate here ever writes into `lexemes`/`grammar_rules` themselves —
 * unmatched rows stay private to the lesson (`status=new`), full stop.
 */
class LessonCandidateMatchingService
{
    public function __construct(
        private readonly CandidateMatchingService $matcher,
        private readonly GrammarProgressServiceInterface $grammarProgress,
    ) {}

    public function matchRun(LessonAnalysisRun $run): void
    {
        $lesson = $run->lesson;

        foreach ($run->lexemeCandidates as $candidate) {
            $this->matchLexemeCandidate($candidate, $lesson);
        }

        foreach ($run->grammarCandidates as $candidate) {
            $this->matchGrammarCandidate($candidate, $lesson);
        }
    }

    private function matchLexemeCandidate(LessonLexemeCandidate $candidate, Lesson $lesson): void
    {
        $result = $this->matcher->findBestLexemeMatch(
            $candidate->normalized_text,
            $candidate->text,
            $lesson->language ?? 'en'
        );

        $candidate->update([
            'matched_lexeme_id' => $result['lexeme_id'],
            'match_score' => $result['score'],
            'status' => $result['lexeme_id'] !== null ? LessonLexemeCandidate::STATUS_MATCHED : LessonLexemeCandidate::STATUS_NEW,
        ]);
    }

    private function matchGrammarCandidate(LessonGrammarCandidate $candidate, Lesson $lesson): void
    {
        $result = $this->matcher->findBestGrammarMatch($candidate->title, (string) $candidate->summary);

        $candidate->update([
            'matched_grammar_rule_id' => $result['grammar_rule_id'],
            'match_score' => $result['score'],
            'status' => $result['grammar_rule_id'] !== null ? LessonGrammarCandidate::STATUS_LINKED : LessonGrammarCandidate::STATUS_NEW,
        ]);

        if ($result['grammar_rule_id'] !== null) {
            $this->grammarProgress->startLearningById($result['grammar_rule_id'], $lesson->user_id);
        }
    }
}
