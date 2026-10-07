<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Application\Contracts\SentencePracticeLearnerContextInterface;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;
use Illuminate\Support\Facades\DB;
use App\Modules\User\Application\Contracts\LearningFlowLearnerReaderInterface;

class SentencePracticeLearnerContext implements SentencePracticeLearnerContextInterface
{
    public function __construct(private readonly LearningFlowLearnerReaderInterface $learnerReader) {}

    public function nativeLanguage(int $userId): string
    {
        $learner = $this->learnerReader->forUser($userId);

        return (string) ($learner['translation_language'] ?? config('ai.analysis.translation_language', 'ru'));
    }

    public function recentGrammar(int $userId, int $limit): array
    {
        return UserGrammarRule::query()
            ->where('user_grammar_rules.user_id', $userId)
            ->whereIn('user_grammar_rules.status', [UserGrammarRule::STATUS_LEARNING, UserGrammarRule::STATUS_LEARNED])
            ->leftJoin('grammar_rules', 'grammar_rules.id', '=', 'user_grammar_rules.grammar_rule_id')
            ->orderByDesc('user_grammar_rules.learned_at')
            ->orderByDesc('user_grammar_rules.started_at')
            ->limit($limit)
            ->get(['grammar_rules.language', 'grammar_rules.title'])
            ->map(fn (UserGrammarRule $row): array => [
                'language' => $row->language,
                'title' => $row->title,
            ])
            ->all();
    }

    public function lexemeConfidenceAverages(int $userId, array $contentLexemeIds): array
    {
        if ($contentLexemeIds === []) {
            return [];
        }

        $lexemeByOccurrence = DB::table('content_lexemes')->whereIn('id', $contentLexemeIds)->pluck('lexeme_id', 'id');
        $confidenceByLexeme = UserLexemeConfidence::query()->where('user_id', $userId)
            ->whereIn('lexeme_id', $lexemeByOccurrence->filter()->unique()->values())->get()->keyBy('lexeme_id');

        return collect($lexemeByOccurrence)->mapWithKeys(function ($lexemeId, $occurrenceId) use ($confidenceByLexeme): array {
                $confidence = $confidenceByLexeme->get($lexemeId);
                if ($confidence === null) {
                    return [];
                }
                $average = (int) round(collect(['recognition', 'recall', 'production', 'listening', 'speaking'])
                    ->map(fn (string $dimension): int => (int) ($confidence->{$dimension} ?? 0))
                    ->avg());

                return [(int) $occurrenceId => $average];
            })
            ->all();
    }

    public function grammarProgress(int $userId, array $grammarRuleIds): array
    {
        if ($grammarRuleIds === []) {
            return [];
        }

        return UserGrammarRule::query()
            ->where('user_id', $userId)
            ->whereIn('grammar_rule_id', $grammarRuleIds)
            ->get()
            ->mapWithKeys(fn (UserGrammarRule $progress): array => [
                $progress->grammar_rule_id => [
                    'status' => $progress->status,
                    'confidence_calculated' => (float) ($progress->confidence_calculated ?? 0),
                ],
            ])
            ->all();
    }
}
