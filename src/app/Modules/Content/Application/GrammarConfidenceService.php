<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Application\Contracts\GrammarConfidenceRecorderInterface;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes UserGrammarRule.confidence_calculated for one (user, rule)
 * pair from two signals:
 *
 * 1. This rule's own grammar_exam_attempts — a direct measurement, the
 *    learner was tested on exactly this topic (average of the last few
 *    attempts, most recent first).
 * 2. The pass rate of SRS reviews on words linked to the rule via the
 *    grammar_rule_lexeme pivot — the same join GetWeakTopicsTool uses to
 *    find weak topics, but counting passes rather than failures. This is
 *    only an indirect proxy (a word can be answered correctly without the
 *    learner truly having internalized the grammar point it demonstrates).
 *
 * When both exist, the exam signal is weighted higher (0.7 vs 0.3) because
 * it directly targets the grammar point. confidence_manual is never
 * touched here — that number belongs to the learner, not the algorithm.
 */
class GrammarConfidenceService
{
    public function __construct(private readonly GrammarConfidenceRecorderInterface $confidenceRecorder) {}

    private const EXAM_WEIGHT = 0.7;

    private const SRS_WEIGHT = 0.3;

    private const RECENT_ATTEMPTS_LIMIT = 5;

    /**
     * Mirrors GetUserMistakesTool::FAILING_GRADE_THRESHOLD without importing
     * across the Ai/Content module boundary for a single constant.
     */
    private const FAILING_GRADE_THRESHOLD = 2;

    public function recalculate(int $userId, GrammarRule $rule): ?float
    {
        $examScore = $this->examScore($userId, $rule);
        $srsScore = $this->srsScore($userId, $rule);

        $calculated = match (true) {
            $examScore !== null && $srsScore !== null => round($examScore * self::EXAM_WEIGHT + $srsScore * self::SRS_WEIGHT, 2),
            $examScore !== null => $examScore,
            $srsScore !== null => $srsScore,
            default => null,
        };

        if ($calculated === null) {
            return null;
        }

        $this->confidenceRecorder->recordCalculated($userId, $rule->id, $calculated);

        return $calculated;
    }

    private function examScore(int $userId, GrammarRule $rule): ?float
    {
        $recentScores = GrammarExamAttempt::query()
            ->where('user_id', $userId)
            ->where('grammar_rule_id', $rule->id)
            ->orderByDesc('completed_at')
            ->limit(self::RECENT_ATTEMPTS_LIMIT)
            ->pluck('score_pct');

        return $recentScores->isEmpty() ? null : round((float) $recentScores->avg(), 2);
    }

    private function srsScore(int $userId, GrammarRule $rule): ?float
    {
        $driver = DB::connection()->getDriverName();

        // Same driver-aware item_key match as GetWeakTopicsTool/SrsService —
        // srs_cards has no direct lexeme FK, only "type:text" + content_id.
        $itemKeyMatch = $driver === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";

        $totals = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->join('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')
                    ->whereRaw($itemKeyMatch);
            })
            ->join('grammar_rule_lexeme', 'grammar_rule_lexeme.lexeme_id', '=', 'content_lexemes.lexeme_id')
            ->where('srs_cards.user_id', $userId)
            ->where('grammar_rule_lexeme.grammar_rule_id', $rule->id)
            ->selectRaw('count(*) as total, sum(case when srs_reviews.grade > ? then 1 else 0 end) as passed', [self::FAILING_GRADE_THRESHOLD])
            ->first();

        $total = (int) ($totals->total ?? 0);
        if ($total === 0) {
            return null;
        }

        return round(((int) $totals->passed / $total) * 100, 2);
    }
}
