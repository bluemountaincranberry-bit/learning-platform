<?php

namespace App\Modules\Ai\Application\Agent\Graph\Nodes;

use App\Modules\Ai\Application\Agent\Contracts\SpecialistAgentService;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Graph\GraphNode;
use App\Modules\Ai\Application\Agent\Graph\GraphState;
use App\Modules\Ai\Application\Agent\Tracing\TraceContext;

/**
 * `AgentNode` (docs/architecture/agent-framework-roadmap.md, section 7) —
 * a whole specialist agent's `AgentLoop` run as one graph step. Generic
 * over any `SpecialistAgentService` (`GrammarAgentService`,
 * `ReviewAgentService`, ...) so `TutorRoutingGraph` (task 4.7) needs one
 * instance per specialist, not a bespoke node class per agent.
 *
 * Reads `task`/`acting_user_id`/`conversation_id`/`trace_id`/
 * `parent_span_id` from `GraphState` (plain, JSON-serializable — the same
 * reason `TraceContext` is rebuilt from two strings here rather than
 * passed as an object), runs the specialist, writes its reply to
 * `$resultStateKey`, then routes to `$routeToAfter` (typically a shared
 * merge step) instead of falling through to whatever step happens to sit
 * next in the definition's array order.
 */
final class AgentNode implements GraphNode
{
    public function __construct(
        private readonly SpecialistAgentService $service,
        private readonly string $resultStateKey,
        private readonly string $routeToAfter,
    ) {}

    public function run(GraphState $state): GraphState
    {
        $task = (string) $state->get('task', '');
        $conversationId = (int) $state->get('conversation_id', 0);
        $actingUserId = (int) $state->get('acting_user_id', 0);

        $context = new AgentToolContext($conversationId, $actingUserId);

        $traceId = $state->get('trace_id');
        $trace = is_string($traceId) && $traceId !== ''
            ? new TraceContext($traceId, is_string($state->get('parent_span_id')) ? $state->get('parent_span_id') : null)
            : TraceContext::newTrace();

        $result = $this->service->run($task, $context, $trace);

        $state->set($this->resultStateKey, $result);
        $state->routeTo($this->routeToAfter);

        return $state;
    }
}
