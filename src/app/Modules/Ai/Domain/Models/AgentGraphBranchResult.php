<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Durable result for one parallel AI graph branch. */
class AgentGraphBranchResult extends Model
{
    protected $guarded = [];

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return ['result' => 'array'];
    }

    public function graphRun(): BelongsTo
    {
        return $this->belongsTo(AgentGraphRun::class, 'graph_run_id');
    }
}
