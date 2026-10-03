<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Memory tool (task 3.2): aggregates the student's failed spaced-repetition
 * reviews (`srs_reviews`, same "grade <= 2" failure convention as
 * `GetUserMistakesTool`) up to the `GrammarRule`s their failed words are
 * linked to (`content_lexemes` -> canonical `lexemes` -> `grammar_rule_lexeme`
 * pivot), and counts failures per rule — real "aggregate over
 * srs_reviews/GrammarRule" per `docs/architecture/ai-platform-vision.md`
 * section 4, not a guess. Words with no linked grammar rule simply don't
 * contribute a topic; this tool only ever reports what it can trace.
 */
class GetWeakTopicsTool implements AgentTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_weak_topics',
            description: "Aggregates the student's failed spaced-repetition reviews by grammar topic, to find recurring problem areas (e.g. \"struggles with Present Perfect\").",
            parameters: [
                'type' => 'object',
                'properties' => [
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of topics to return (default 5, max 20).',
                    ],
                ],
                'required' => [],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $limit = min(20, max(1, (int) ($arguments['limit'] ?? 5)));
        $driver = DB::connection()->getDriverName();

        // Same driver-aware item_key match as SrsService::getDueCards() —
        // srs_cards has no direct lexeme FK, only "type:text" + content_id.
        $itemKeyMatch = $driver === 'sqlite'
            ? "(content_lexemes.type || ':' || content_lexemes.text) = srs_cards.item_key"
            : "CONCAT(content_lexemes.type, ':', content_lexemes.text) = srs_cards.item_key";

        $topics = DB::table('srs_reviews')
            ->join('srs_cards', 'srs_cards.id', '=', 'srs_reviews.srs_card_id')
            ->join('content_lexemes', function ($join) use ($itemKeyMatch): void {
                $join->on('content_lexemes.content_id', '=', 'srs_cards.content_id')
                    ->whereRaw($itemKeyMatch);
            })
            ->join('grammar_rule_lexeme', 'grammar_rule_lexeme.lexeme_id', '=', 'content_lexemes.lexeme_id')
            ->join('grammar_rules', 'grammar_rules.id', '=', 'grammar_rule_lexeme.grammar_rule_id')
            ->where('srs_cards.user_id', $context->actingUserId)
            ->where('srs_reviews.grade', '<=', GetUserMistakesTool::FAILING_GRADE_THRESHOLD)
            ->select('grammar_rules.id', 'grammar_rules.title', DB::raw('count(*) as mistake_count'))
            ->groupBy('grammar_rules.id', 'grammar_rules.title')
            ->orderByDesc('mistake_count')
            ->limit($limit)
            ->get();

        if ($topics->isEmpty()) {
            return [
                'weak_topics' => [],
                'note' => 'Not enough data yet to identify weak topics — no failed reviews are linked to a grammar rule.',
            ];
        }

        return [
            'weak_topics' => $topics->map(fn ($topic) => [
                'grammar_rule_id' => (int) $topic->id,
                'title' => $topic->title,
                'mistake_count' => (int) $topic->mistake_count,
            ])->all(),
        ];
    }
}
