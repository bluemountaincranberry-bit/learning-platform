<?php

declare(strict_types=1);

use App\Contracts\KafkaProducerInterface;
use App\Modules\Content\Domain\Events\ContentSubmitted;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Interfaces\Listeners\PublishContentSubmittedToKafka;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

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

test('when ContentSubmitted is dispatched and Kafka listener is registered, producer send is called with expected topic key and payload', function () {
    Bus::fake();
    $this->app->instance(KafkaProducerInterface::class, $this->fakeProducer);
    Event::listen(ContentSubmitted::class, PublishContentSubmittedToKafka::class);

    $content = Content::factory()->create([
        'type' => 'youtube',
        'status' => 'pending',
        'created_by' => null,
    ]);

    event(new ContentSubmitted($content->id));

    expect($this->fakeProducer->calls)->toHaveCount(1);
    $call = $this->fakeProducer->calls[0];
    expect($call['topic'])->toBe(PublishContentSubmittedToKafka::TOPIC)
        ->and($call['key'])->toBe((string) $content->getKey());

    $decoded = json_decode($call['payload'], true);
    expect($decoded)->toHaveKeys(['event_type', 'version', 'timestamp', 'payload'])
        ->and($decoded['event_type'])->toBe(PublishContentSubmittedToKafka::EVENT_TYPE)
        ->and($decoded['version'])->toBe(PublishContentSubmittedToKafka::VERSION)
        ->and($decoded['payload'])->toHaveKeys(['content_id', 'type', 'status', 'user_id'])
        ->and($decoded['payload']['content_id'])->toBe($content->getKey());
});

test('when Kafka is disabled (default), ContentSubmitted Kafka listener is not registered', function () {
    // With default config (KAFKA_ENABLED=false), EventServiceProvider does not register PublishContentSubmittedToKafka
    $listeners = Event::getListeners(ContentSubmitted::class);
    $listenerClasses = array_map(function ($l) {
        if (is_string($l)) {
            return $l;
        }
        if (is_array($l) && isset($l[0])) {
            return is_object($l[0]) ? $l[0]::class : $l[0];
        }

        return null;
    }, $listeners);

    expect($listenerClasses)->not->toContain(PublishContentSubmittedToKafka::class);
});
