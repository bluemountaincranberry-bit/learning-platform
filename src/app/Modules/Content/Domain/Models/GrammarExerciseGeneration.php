<?php

namespace App\Modules\Content\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued AI batch of grammar exercises for a rule (VIK-31). The round
 * screen polls its status; the rate limit counts these rows.
 */
class GrammarExerciseGeneration extends Model
{
    protected $guarded = [];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const ACTIVE_STATUSES = [self::STATUS_QUEUED, self::STATUS_RUNNING];

    /** An active row older than this is treated as lost (worker died) and no longer blocks a new batch. */
    public const STALE_AFTER_MINUTES = 10;

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', self::ACTIVE_STATUSES)
            ->where('created_at', '>=', now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    public function grammarRule(): BelongsTo
    {
        return $this->belongsTo(GrammarRule::class);
    }
}
