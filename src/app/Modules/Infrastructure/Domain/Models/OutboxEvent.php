<?php

namespace App\Modules\Infrastructure\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OutboxEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $table = 'outbox_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public static function record(string $eventType, string $aggregateType, int|string $aggregateId, array $payload, int $version = 1): self
    {
        return static::query()->create(['id' => (string) Str::uuid(), 'event_type' => $eventType, 'aggregate_type' => $aggregateType, 'aggregate_id' => (string) $aggregateId, 'payload' => $payload, 'version' => $version, 'status' => 'pending', 'attempts' => 0, 'available_at' => now()]);
    }
}
