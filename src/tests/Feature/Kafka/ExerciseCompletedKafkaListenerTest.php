<?php

declare(strict_types=1);

use App\Contracts\KafkaProducerInterface;
use App\Modules\Learning\Domain\Events\ExerciseCompleted;
use App\Modules\Learning\Interfaces\Listeners\PublishExerciseCompletedToKafka;
use Illuminate\Support\Facades\Event;

/**
 * Same shape as tests/Feature/Kafka/KafkaProducerListenerTest.php (the
 * ContentSubmitted listener's test) — a fake KafkaProducerInterface, no
 * live broker needed. NullKafkaProducer (bound by default, KAFKA-02) is
 * what actually runs in this environment/tests otherwise.
 */
beforeEach(function () {
    $this->fakeProducer = new class implements KafkaProducerInterface
    {
        public array $calls = [];

        public function send(string $topic, ?string $key, string $payload): void
        {
            $this->calls[] = ['topic' => $topic, 'key' => $key, 'payload' => $payload];
        }
    };
});

test('when ExerciseCompleted is dispatched and the Kafka listener is registered, producer send is called with expected topic key and payload', function () {
    $this->app->instance(KafkaProducerInterface::class, $this->fakeProducer);
    Event::listen(ExerciseCompleted::class, PublishExerciseCompletedToKafka::class);

    event(new ExerciseCompleted(userId: 42, item: 'run', grade: 1, isMistake: true, language: 'en'));

    expect($this->fakeProducer->calls)->toHaveCount(1);
    $call = $this->fakeProducer->calls[0];
    expect($call['topic'])->toBe(PublishExerciseCompletedToKafka::TOPIC)
        ->and($call['key'])->toBe('42');

    $decoded = json_decode($call['payload'], true);
    expect($decoded)->toHaveKeys(['event_type', 'version', 'timestamp', 'payload'])
        ->and($decoded['event_type'])->toBe(PublishExerciseCompletedToKafka::EVENT_TYPE)
        ->and($decoded['version'])->toBe(PublishExerciseCompletedToKafka::VERSION)
        ->and($decoded['payload'])->toBe([
            'user_id' => 42,
            'item' => 'run',
            'grade' => 1,
            'is_mistake' => true,
            'language' => 'en',
        ]);
});

test('when Kafka is disabled (default), the ExerciseCompleted Kafka listener is not registered', function () {
    // LearningServiceProvider only registers this listener when
    // config('kafka.enabled') is true (KAFKA_ENABLED=false by default) —
    // same conditional-registration pattern as ContentServiceProvider.
    $listeners = Event::getListeners(ExerciseCompleted::class);
    $listenerClasses = array_map(function ($l) {
        if (is_string($l)) {
            return $l;
        }
        if (is_array($l) && isset($l[0])) {
            return is_object($l[0]) ? $l[0]::class : $l[0];
        }

        return null;
    }, $listeners);

    expect($listenerClasses)->not->toContain(PublishExerciseCompletedToKafka::class);
});

test('a publish failure is caught and logged, not thrown', function () {
    $throwingProducer = new class implements KafkaProducerInterface
    {
        public function send(string $topic, ?string $key, string $payload): void
        {
            throw new RuntimeException('broker unreachable');
        }
    };
    $this->app->instance(KafkaProducerInterface::class, $throwingProducer);

    $listener = app(PublishExerciseCompletedToKafka::class);

    // Must not throw.
    $listener->handle(new ExerciseCompleted(userId: 1, item: 'x', grade: 5, isMistake: false));

    expect(true)->toBeTrue();
});
