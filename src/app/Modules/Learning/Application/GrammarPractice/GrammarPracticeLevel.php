<?php

namespace App\Modules\Learning\Application\GrammarPractice;

/**
 * Same Easy/Medium/Hard model as the start-learning flow (VIK-29):
 * Easy = recognize, Hard = produce, Medium = a round that goes easy → hard.
 */
enum GrammarPracticeLevel: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    /** Round order of exercise types, easy → hard. */
    public const TYPE_ORDER = ['multiple_choice', 'build', 'cloze', 'transform', 'fix'];

    /** @return list<string> */
    public function types(): array
    {
        return match ($this) {
            self::Easy => ['multiple_choice', 'build'],
            self::Hard => ['cloze', 'transform', 'fix'],
            self::Medium => self::TYPE_ORDER,
        };
    }

    /** "Mark as learned" is offered only for rounds that asked the learner to produce the form. */
    public function canProveLearned(): bool
    {
        return $this !== self::Easy;
    }
}
