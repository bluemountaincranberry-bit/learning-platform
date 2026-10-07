<?php

namespace App\Modules\Learning\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Task 4.13 — fired whenever a student finishes one exercise/review item,
 * regardless of which learning activity produced it (SRS review today;
 * self-check/training-session results are natural future emitters of the
 * same event, hence living under `Modules\Learning`, not tied to any one
 * activity's module). `PublishExerciseCompletedToKafka` publishes this to
 * the `exercise.lifecycle` Kafka topic — the fact this event carries is
 * "a review happened, here's the outcome", not a command to anything.
 *
 * Kept intentionally small/flat (no nested value objects) to mirror
 * `ContentSubmitted`'s shape and because this is exactly what the Kafka
 * payload needs — see `ai-platform-vision.md`, section 9.2's
 * `exercise.completed` example payload.
 */
class ExerciseCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $item,
        public readonly int $grade,
        public readonly bool $isMistake,
        public readonly ?string $language = null,
        public readonly ?int $lexemeId = null,
    ) {}
}
