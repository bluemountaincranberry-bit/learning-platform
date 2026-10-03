<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Denormalized trace summary for AI observability. */
class AgentTrace extends Model
{
    protected $guarded = [];

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'total_cost_usd' => 'float'];
    }
}
