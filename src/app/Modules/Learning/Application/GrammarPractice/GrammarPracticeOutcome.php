<?php

namespace App\Modules\Learning\Application\GrammarPractice;

/** How one exercise of a practice round ended. */
enum GrammarPracticeOutcome: string
{
    case FirstTry = 'first_try';
    case AfterHint = 'after_hint';
    case AnswerShown = 'answer_shown';
    case Reported = 'reported';

    public function isScored(): bool
    {
        return $this !== self::Reported;
    }

    /** Worth reviewing on the result screen and replaying in "Practice mistakes". */
    public function isMistake(): bool
    {
        return $this === self::AfterHint || $this === self::AnswerShown;
    }
}
