<?php

namespace App\Modules\Ai\Application\Agent\Data;

use App\Modules\Ai\Application\Agent\Tracing\TraceContext;

/**
 * Per-turn context passed to every tool alongside the model-supplied
 * arguments — scoping info the model itself must never control (which
 * conversation/admin is acting), kept separate from the JSON-schema args.
 *
 * `handoffDepth` and `trace` (both added for task 4.3's `HandoffTool`,
 * optional/defaulted so every existing call site with the original 2-arg
 * constructor keeps compiling unchanged) let a handoff propagate "how deep
 * are we" (enforced against `HandoffTool::MAX_HANDOFF_DEPTH`, ADR-006) and
 * the current trace (so the target agent's spans nest under the handoff's
 * span instead of starting a disconnected new trace) into the nested
 * `AgentLoop::run()` call — see `HandoffTool`'s docblock.
 */
final class AgentToolContext
{
    public function __construct(
        public readonly int $conversationId,
        public readonly int $actingUserId,
        public readonly int $handoffDepth = 0,
        public readonly ?TraceContext $trace = null,
    ) {}
}
