<?php

namespace App\Modules\Content\Application\Data;

/**
 * A grammar exercise as the practice round sees it. Carries the answer data
 * so the round can reveal it after the second try; the learner-facing payload
 * leaves it out until then.
 */
final readonly class GrammarPracticeExercise
{
    /**
     * @param  list<string>|null  $options
     * @param  list<string>|null  $tiles
     * @param  list<string>  $acceptedAnswers
     */
    public function __construct(
        public int $id,
        public int $ruleId,
        public string $type,
        public string $level,
        public ?string $instruction,
        public string $prompt,
        public ?array $options,
        public ?array $tiles,
        public string $answer,
        public ?int $answerIndex,
        public array $acceptedAnswers,
        public ?string $hint,
        public ?string $explanation,
        public string $origin,
    ) {}
}
