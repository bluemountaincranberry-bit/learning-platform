<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Durable execution state for a resumable AI graph workflow. */
class AgentGraphRun extends Model
{
    protected $guarded = [];

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [self::STATUS_RUNNING, self::STATUS_PAUSED, self::STATUS_COMPLETED, self::STATUS_FAILED];

    protected function casts(): array
    {
        return ['state' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
