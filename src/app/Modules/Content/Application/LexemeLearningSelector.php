<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use Illuminate\Support\Collection;

/**
 * Explainable first-pass selection. It deliberately uses signals already
 * available in the product; a frequency provider can replace this later
 * without changing the API contract.
 */
class LexemeLearningSelector
{
    /** @return array{category: string, score: int, reasons: array<int, string>} */
    /** @param array{mistakes?: int, confidence?: ?array<string, int>, due?: bool} $signals */
    public function classify(ContentLexeme $lexeme, Content $content, ?string $learningGoal, ?string $currentLevel, Collection $knownIds, array $signals = []): array
    {
        $text = trim($lexeme->text);
        $frequency = (int) ($lexeme->frequency ?? 0);
        $reasons = [];

        if ($lexeme->lexeme_id !== null && $knownIds->has($lexeme->lexeme_id)) {
            return ['category' => 'known', 'score' => 0, 'reasons' => ['already_known']];
        }

        if ($lexeme->canonicalLexeme?->relationLoaded('rules') && $lexeme->canonicalLexeme->rules->isNotEmpty()) {
            return ['category' => 'grammar_pattern', 'score' => 85, 'reasons' => ['linked_to_grammar_rule', 'useful_in_context']];
        }

        $score = $lexeme->type === ContentLexeme::TYPE_PHRASE ? 80 + min($frequency, 20) : 40;
        if ($lexeme->type === ContentLexeme::TYPE_PHRASE) {
            $reasons[] = 'useful_in_context';
        }

        if ($this->looksLikeNoise($text)) {
            return ['category' => 'noise', 'score' => 5, 'reasons' => ['proper_name_or_noise']];
        }

        if ($frequency >= 3) {
            $score += 25;
            $reasons[] = 'repeated_in_content';
        } elseif ($frequency === 2) {
            $score += 10;
            $reasons[] = 'appears_twice';
        }

        $mistakes = (int) ($signals['mistakes'] ?? 0);
        if ($mistakes > 0) {
            $score += min(20, $mistakes * 5);
            $reasons[] = 'repeated_mistakes';
        }
        $confidence = $signals['confidence'] ?? null;
        if ($confidence !== null) {
            $average = (int) round(collect($confidence)->avg());
            if ($average < 60) {
                $score += min(20, 60 - $average);
                $reasons[] = 'low_skill_confidence';
            }
        }
        if (($signals['due'] ?? false) === true) {
            $score += 15;
            $reasons[] = 'due_for_review';
        }

        $goal = $learningGoal;
        if ($goal === 'conversation' && $lexeme->type === ContentLexeme::TYPE_PHRASE) {
            $score += 10;
            $reasons[] = 'matches_conversation_goal';
        } elseif ($goal === 'exam' && $lexeme->canonicalLexeme?->rules?->isNotEmpty()) {
            $score += 10;
            $reasons[] = 'matches_exam_goal';
        } elseif ($goal === 'travel' && in_array($lexeme->type, [ContentLexeme::TYPE_PHRASE, ContentLexeme::TYPE_WORD], true)) {
            $reasons[] = 'travel_relevant_candidate';
        }

        $level = $currentLevel;
        if ($level !== null && $content->level !== null && $content->level === $level) {
            $score += 10;
            $reasons[] = 'matches_learner_level';
        }
        if ($lexeme->canonicalLexeme?->level !== null && $level !== null) {
            $target = array_search($level, Content::CEFR_LEVELS, true);
            $wordLevel = array_search($lexeme->canonicalLexeme->level, Content::CEFR_LEVELS, true);
            if ($target !== false && $wordLevel !== false && $wordLevel <= $target + 1) {
                $score += 10;
                $reasons[] = 'appropriate_difficulty';
            } elseif ($wordLevel !== false && $target !== false && $wordLevel > $target + 1) {
                $score -= 20;
                $reasons[] = 'above_learner_level';
            }
        }

        if ($frequency <= 1 && $lexeme->canonicalLexeme?->level === 'C2') {
            return ['category' => 'rare', 'score' => max(10, $score - 25), 'reasons' => ['low_frequency', 'advanced_level']];
        }

        if ($lexeme->type === ContentLexeme::TYPE_PHRASE) {
            return ['category' => 'useful_phrase', 'score' => max(0, min(100, $score)), 'reasons' => $reasons];
        }
        $category = $score >= 65 ? 'essential' : 'recommended';
        if ($reasons === []) {
            $reasons[] = 'useful_for_current_content';
        }

        return ['category' => $category, 'score' => max(0, min(100, $score)), 'reasons' => $reasons];
    }

    private function looksLikeNoise(string $text): bool
    {
        return mb_strlen($text) > 30
            || preg_match('/^https?:\/\//i', $text) === 1
            || (preg_match('/^[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+$/', $text) === 1 && str_word_count($text) <= 4);
    }
}
