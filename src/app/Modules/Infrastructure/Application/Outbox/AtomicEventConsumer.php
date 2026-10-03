<?php

namespace App\Modules\Infrastructure\Application\Outbox;

use App\Modules\Infrastructure\Domain\Models\EventConsumption;
use Illuminate\Support\Facades\DB;

final class AtomicEventConsumer
{
    public function consume(OutboxEnvelope $event, string $consumer, callable $handler): bool
    {
        return DB::transaction(function () use ($event, $consumer, $handler): bool {
            $claimed = EventConsumption::query()->insertOrIgnore([
                'event_id' => $event->eventId, 'consumer' => $consumer,
                'version' => $event->version, 'consumed_at' => now(),
            ]);
            if ($claimed === 0) return false;
            $handler($event);
            return true;
        });
    }
}
