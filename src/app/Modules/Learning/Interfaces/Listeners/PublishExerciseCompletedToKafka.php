<?php

namespace App\Modules\Learning\Interfaces\Listeners;

use App\Contracts\KafkaProducerInterface;
use App\Modules\Learning\Domain\Events\ExerciseCompleted;
use Illuminate\Support\Facades\Log;

/**
 * Task 4.13 — same exact shape as
 * `App\Modules\Content\Interfaces\Listeners\PublishContentSubmittedToKafka`
 * (topic/event_type/version constants, envelope shape, try/catch-and-warn
 * on publish failure instead of throwing): this project's established
 * Kafka-listener pattern, not a new mechanism. Bound to `NullKafkaProducer`
 * by default (`AppServiceProvider`, `KAFKA-02`) exactly like the content
 * listener, so this is safe to register unconditionally in tests/dev.
 *
 * This is the event-backbone half of "memory model" from
 * `ai-platform-vision.md` section 9.2: publishing is all this listener
 * does. A consumer turning these events into queryable semantic-memory
 * observations (`GetWeakTopicsTool`-style tools reading structured
 * insights instead of recomputing them live) is EPIC 2.6, which this task
 * unblocks but does not itself implement — `ConsumeExerciseEventsCommand`
 * only reliably captures the event stream (writes to `event_log`, same
 * durability `ConsumeKafkaCommand` already gives every topic); it does not
 * write to Elasticsearch, because the semantic-memory index (EPIC 2.6)
 * doesn't exist yet.
 */
class PublishExerciseCompletedToKafka
{
    public const TOPIC = 'exercise.lifecycle';

    public const EVENT_TYPE = 'ExerciseCompleted';

    public const VERSION = 1;

    public function __construct(
        private KafkaProducerInterface $producer
    ) {}

    public function handle(ExerciseCompleted $event): void
    {
        $key = (string) $event->userId;
        $payload = [
            'event_type' => self::EVENT_TYPE,
            'version' => self::VERSION,
            'timestamp' => now()->toIso8601String(),
            'payload' => [
                'user_id' => $event->userId,
                'item' => $event->item,
                'grade' => $event->grade,
                'is_mistake' => $event->isMistake,
                'language' => $event->language,
            ],
        ];

        try {
            $this->producer->send(self::TOPIC, $key, json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('Kafka publish failed (ExerciseCompleted)', [
                'user_id' => $event->userId,
                'topic' => self::TOPIC,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
