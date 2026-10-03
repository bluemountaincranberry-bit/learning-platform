<?php

namespace App\Modules\Content\Interfaces\Listeners;

use App\Contracts\KafkaProducerInterface;
use App\Modules\Content\Domain\Events\ContentSubmitted;
use App\Modules\Content\Domain\Models\Content;
use Illuminate\Support\Facades\Log;

class PublishContentSubmittedToKafka
{
    public const TOPIC = 'content.lifecycle';

    public const EVENT_TYPE = 'ContentSubmitted';

    public const VERSION = 1;

    public function __construct(
        private KafkaProducerInterface $producer
    ) {}

    public function handle(ContentSubmitted $event): void
    {
        $content = Content::query()->find($event->contentId);
        if ($content === null) {
            return;
        }
        $key = (string) $content->getKey();
        $payload = [
            'event_type' => self::EVENT_TYPE,
            'version' => self::VERSION,
            'timestamp' => now()->toIso8601String(),
            'payload' => [
                'content_id' => $content->getKey(),
                'type' => $content->type ?? null,
                'status' => $content->status ?? null,
                'user_id' => $content->created_by ?? null,
            ],
        ];

        try {
            $this->producer->send(self::TOPIC, $key, json_encode($payload));
        } catch (\Throwable $e) {
            Log::warning('Kafka publish failed (ContentSubmitted)', [
                'content_id' => $content->getKey(),
                'topic' => self::TOPIC,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
