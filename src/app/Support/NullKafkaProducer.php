<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\KafkaProducerInterface;

/**
 * No-op producer when Kafka is disabled. Drops all messages.
 */
class NullKafkaProducer implements KafkaProducerInterface
{
    public function send(string $topic, ?string $key, string $payload): void
    {
        // no-op
    }
}
