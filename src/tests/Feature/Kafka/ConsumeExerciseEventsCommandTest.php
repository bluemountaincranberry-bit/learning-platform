<?php

declare(strict_types=1);

use App\Console\Commands\ConsumeExerciseEventsCommand;
use App\Modules\Infrastructure\Domain\Models\EventLog;
use App\Modules\Learning\Interfaces\Listeners\PublishExerciseCompletedToKafka;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Exercises handleMessage() directly — the part of ConsumeExerciseEventsCommand
 * that doesn't need a live broker/rdkafka (see that class's docblock).
 */
uses(RefreshDatabase::class);

function exerciseEventPayload(bool $isMistake): string
{
    return json_encode([
        'event_type' => PublishExerciseCompletedToKafka::EVENT_TYPE,
        'version' => PublishExerciseCompletedToKafka::VERSION,
        'timestamp' => now()->toIso8601String(),
        'payload' => [
            'user_id' => 7,
            'item' => 'give up',
            'grade' => $isMistake ? 1 : 4,
            'is_mistake' => $isMistake,
            'language' => 'en',
        ],
    ]);
}

test('handleMessage writes every consumed message to event_log regardless of content', function () {
    (new ConsumeExerciseEventsCommand)->handleMessage('exercise.lifecycle', 0, 123, '7', exerciseEventPayload(false));

    $row = EventLog::query()->where('topic', 'exercise.lifecycle')->where('offset', 123)->first();
    expect($row)->not->toBeNull()
        ->and($row->key)->toBe('7')
        ->and($row->partition)->toBe(0);
});

test('handleMessage does not throw on a non-ExerciseCompleted or malformed payload', function () {
    (new ConsumeExerciseEventsCommand)->handleMessage('exercise.lifecycle', 0, 1, null, 'not json at all');
    (new ConsumeExerciseEventsCommand)->handleMessage('exercise.lifecycle', 0, 2, null, json_encode(['event_type' => 'SomethingElse']));

    expect(EventLog::query()->count())->toBe(2);
});

test('handleMessage accepts both mistake and non-mistake events without error', function () {
    (new ConsumeExerciseEventsCommand)->handleMessage('exercise.lifecycle', 0, 10, '7', exerciseEventPayload(true));
    (new ConsumeExerciseEventsCommand)->handleMessage('exercise.lifecycle', 0, 11, '7', exerciseEventPayload(false));

    expect(EventLog::query()->count())->toBe(2);
});
