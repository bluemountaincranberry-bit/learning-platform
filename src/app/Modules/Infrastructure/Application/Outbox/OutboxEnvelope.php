<?php

namespace App\Modules\Infrastructure\Application\Outbox;

final readonly class OutboxEnvelope
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public int $version,
        public string $aggregateType,
        public string $aggregateId,
        public array $payload,
    ) {}
}
