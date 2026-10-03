<?php

use App\Modules\Content\Domain\Events\TranscriptAccepted;
use App\Modules\Infrastructure\Application\Outbox\AtomicEventConsumer;
use App\Modules\Infrastructure\Application\Outbox\OutboxDispatcher;
use App\Modules\Infrastructure\Application\Outbox\OutboxEnvelope;
use App\Modules\Infrastructure\Domain\Models\EventConsumption;
use App\Modules\Infrastructure\Domain\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('dispatcher publishes a recorded domain event and marks it published', function () {
    Event::fake();
    $event = OutboxEvent::record(TranscriptAccepted::class, 'content', 42, ['content_id' => 42, 'actor_id' => 7]);
    $event->update(['available_at' => now()->subMinute()]);

    expect(app(OutboxDispatcher::class)->dispatch())->toBe(1)
        ->and($event->refresh()->status)->toBe('published');
    Event::assertDispatched(TranscriptAccepted::class, fn ($published) => $published->contentId === 42);
});

test('atomic consumer ignores a duplicate event for the same consumer', function () {
    $consumer = app(AtomicEventConsumer::class);
    $event = new OutboxEnvelope('event-1', TranscriptAccepted::class, 1, 'content', '42', ['content_id' => 42, 'actor_id' => null]);
    $calls = 0;

    expect($consumer->consume($event, 'search-indexer', function () use (&$calls): void { $calls++; }))->toBeTrue()
        ->and($consumer->consume($event, 'search-indexer', function () use (&$calls): void { $calls++; }))->toBeFalse()
        ->and($calls)->toBe(1)
        ->and(EventConsumption::query()->count())->toBe(1);
});
