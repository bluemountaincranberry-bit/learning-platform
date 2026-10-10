<?php

namespace App\Modules\Learning\Application;

use App\Modules\Learning\Domain\Models\LessonGrammarCandidate;
use App\Modules\Learning\Domain\Models\LessonLexemeCandidate;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Reads the learner-specific selection and practice state for lesson items. */
final class LessonPersonalItemStateReader
{
    /** @return array{lexemes: array<int, array{in_my_words: bool, in_review: bool, learned: bool}>, grammar: array<int, bool>} */
    public function forItems(Collection $lexemes, Collection $grammar, int $userId): array
    {
        return [
            'lexemes' => $this->lexemeStatesFor($lexemes, $userId),
            'grammar' => $this->grammarStatesFor($grammar, $userId),
        ];
    }

    /** @return array<int, array{in_my_words: bool, in_review: bool, learned: bool}> */
    private function lexemeStatesFor(Collection $lexemes, int $userId): array
    {
        $sources = UserLexemeSource::query()->where('user_id', $userId)
            ->whereIn('lesson_lexeme_candidate_id', $lexemes->pluck('id'))
            ->pluck('lesson_lexeme_candidate_id')->flip();
        $activeLexemes = DB::table('srs_cards')->where('user_id', $userId)
            ->whereIn('lexeme_id', $lexemes->pluck('matched_lexeme_id')->filter()->unique())
            ->whereNull('deactivated_at')->pluck('lexeme_id')->flip();
        $learnedLexemes = DB::table('user_lexeme_progress')->where('user_id', $userId)
            ->whereIn('lexeme_id', $lexemes->pluck('matched_lexeme_id')->filter()->unique())
            ->whereNotNull('learned_at')->pluck('lexeme_id')->flip();

        return $lexemes->mapWithKeys(fn (LessonLexemeCandidate $candidate) => [
            $candidate->id => [
                'in_my_words' => $sources->has($candidate->id),
                'in_review' => $candidate->matched_lexeme_id !== null && $activeLexemes->has($candidate->matched_lexeme_id),
                'learned' => $candidate->matched_lexeme_id !== null && $learnedLexemes->has($candidate->matched_lexeme_id),
            ],
        ])->all();
    }

    /** @return array<int, bool> */
    private function grammarStatesFor(Collection $grammar, int $userId): array
    {
        $ruleIds = $grammar->flatMap(fn (LessonGrammarCandidate $candidate) => [
            $candidate->personal_grammar_rule_id,
            $candidate->matched_grammar_rule_id,
        ])->filter()->unique();
        $selectedRules = UserGrammarRule::query()->where('user_id', $userId)
            ->whereIn('grammar_rule_id', $ruleIds)->pluck('grammar_rule_id')->flip();

        return $grammar->mapWithKeys(function (LessonGrammarCandidate $candidate) use ($selectedRules): array {
            $ruleId = $candidate->personal_grammar_rule_id ?? $candidate->matched_grammar_rule_id;

            return [$candidate->id => $ruleId !== null && $selectedRules->has($ruleId)];
        })->all();
    }

    /** @return array{in_my_words: bool, in_review: bool, learned: bool} */
    public function forLexeme(LessonLexemeCandidate $candidate, int $userId): array
    {
        return $this->lexemeStatesFor(collect([$candidate]), $userId)[$candidate->id];
    }

    public function forGrammar(LessonGrammarCandidate $candidate, int $userId): bool
    {
        return $this->grammarStatesFor(collect([$candidate]), $userId)[$candidate->id];
    }
}
