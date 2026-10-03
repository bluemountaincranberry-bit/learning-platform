<?php

namespace App\Modules\Infrastructure\Application\Outbox;

use App\Modules\Infrastructure\Domain\Models\OutboxEvent;
use Illuminate\Support\Facades\Event;

final class OutboxDispatcher
{
    public function dispatch(int $limit = 100): int
    {
        $events = OutboxEvent::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 10)
            ->where(function ($query): void { $query->whereNull('available_at')->orWhere('available_at', '<=', now()); })
            ->orderBy('created_at')
            ->take($limit)
            ->get();

        $published = 0;
        foreach ($events as $outboxEvent) {
            try {
                $envelope = new OutboxEnvelope(
                    (string) $outboxEvent->id,
                    (string) $outboxEvent->event_type,
                    (int) $outboxEvent->version,
                    (string) $outboxEvent->aggregate_type,
                    (string) $outboxEvent->aggregate_id,
                    (array) $outboxEvent->payload,
                );
                $domainEvent = match ($envelope->eventType) {
                    'App\\Modules\\Content\\Domain\\Events\\TranscriptAccepted' => new \App\Modules\Content\Domain\Events\TranscriptAccepted($envelope->payload['content_id'], $envelope->payload['actor_id'] ?? null),
                    'App\\Modules\\Content\\Domain\\Events\\ContentAnalysisCompleted' => new \App\Modules\Content\Domain\Events\ContentAnalysisCompleted($envelope->payload['content_id'], $envelope->payload['analysis_run_id']),
                    default => $envelope,
                };
                Event::dispatch($domainEvent);
                $outboxEvent->update(['status' => 'published', 'published_at' => now(), 'last_error' => null]);
                $published++;
            } catch (\Throwable $exception) {
                $attempts = (int) $outboxEvent->attempts + 1;
                $outboxEvent->update([
                    'status' => $attempts >= 10 ? 'failed' : 'pending',
                    'attempts' => $attempts,
                    'available_at' => now()->addSeconds(min(300, 2 ** min($attempts, 8))),
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
            }
        }

        return $published;
    }
}
