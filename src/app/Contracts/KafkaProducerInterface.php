<?php

declare(strict_types=1);

namespace App\Contracts;

interface KafkaProducerInterface
{
    /**
     * Publish a message to a Kafka topic.
     */
    public function send(string $topic, ?string $key, string $payload): void;
}
