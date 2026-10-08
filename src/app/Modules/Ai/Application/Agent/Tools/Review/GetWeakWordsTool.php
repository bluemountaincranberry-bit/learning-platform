<?php

namespace App\Modules\Ai\Application\Agent\Tools\Review;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Student\GetUserMistakesTool;
use Illuminate\Support\Facades\DB;

/**
 * ReviewAgent tool 1/3 (task 4.6): ranks the acting student's own SRS cards
 * by how much trouble they cause — fail rate first, then how low their ease
 * factor has drifted (SM-2-style: repeated failures push ease down) — so
 * `CreateReviewPlanTool` has something concrete to plan around instead of
 * the model guessing which words need attention. Same "grade <= 2 is a
 * failure" convention as `GetUserMistakesTool`/`GetWeakTopicsTool`, but
 * aggregated per-card (word) rather than listed per-event or rolled up to
 * grammar topic — a different shape for a different purpose (a review
 * plan operates on individual cards, not topics).
 */
class GetWeakWordsTool implements AgentTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_weak_words',
            description: "Ranks the student's own spaced-repetition cards by how much trouble they cause (fail rate, ease factor) — the words most worth planning review around.",
            parameters: [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of words to return (default 10, max 30).',
                    ],
                ],
                'required' => [],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 10)));

        $cards = DB::table('srs_cards')
            ->leftJoin('srs_reviews', 'srs_reviews.srs_card_id', '=', 'srs_cards.id')
            ->join('lexemes as canonical_lexemes', 'canonical_lexemes.id', '=', 'srs_cards.lexeme_id')
            ->where('srs_cards.user_id', $context->actingUserId)
            ->whereNotNull('srs_cards.lexeme_id')
            ->select(
                'srs_cards.id',
                'srs_cards.lexeme_id',
                'canonical_lexemes.lemma',
                'srs_cards.ease_factor',
                'srs_cards.next_review_at',
                DB::raw('count(srs_reviews.id) as total_reviews'),
                DB::raw('sum(case when srs_reviews.grade <= '.GetUserMistakesTool::FAILING_GRADE_THRESHOLD.' then 1 else 0 end) as fail_count')
            )
            ->groupBy('srs_cards.id', 'srs_cards.lexeme_id', 'canonical_lexemes.lemma', 'srs_cards.ease_factor', 'srs_cards.next_review_at')
            ->havingRaw('count(srs_reviews.id) > 0')
            ->get();

        if ($cards->isEmpty()) {
            return [
                'weak_words' => [],
                'note' => 'Not enough review history yet to identify weak words.',
            ];
        }

        $ranked = $cards
            ->map(function ($card) {
                $total = (int) $card->total_reviews;
                $fails = (int) $card->fail_count;

                return [
                    'card_id' => (int) $card->id,
                    'lexeme_id' => $card->lexeme_id !== null ? (int) $card->lexeme_id : null,
                    'item' => (string) $card->lemma,
                    'fail_rate' => $total > 0 ? round($fails / $total, 2) : 0.0,
                    'ease_factor' => (float) $card->ease_factor,
                    'total_reviews' => $total,
                ];
            })
            ->sort(fn ($a, $b) => ($b['fail_rate'] <=> $a['fail_rate']) ?: ($a['ease_factor'] <=> $b['ease_factor']))
            ->values()
            ->take($limit);

        return ['weak_words' => $ranked->all()];
    }
}
