<?php

namespace App\Modules\Ai\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/** Detailed AI trace span; the source of truth for the trace tree. */
class AgentTraceSpan extends Model
{
    protected $guarded = [];

    public const STATUS_OK = 'ok';

    public const STATUS_ERROR = 'error';

    public const SPAN_TYPE_LLM_CALL = 'llm_call';

    public const SPAN_TYPE_TOOL_CALL = 'tool_call';

    public const SPAN_TYPE_AGENT_TURN = 'agent_turn';

    public const SPAN_TYPE_HANDOFF = 'handoff';

    protected function casts(): array
    {
        return ['metadata' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime', 'exported_at' => 'datetime', 'cost_usd' => 'float'];
    }
}
