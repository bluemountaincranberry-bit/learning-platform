<?php

namespace App\Modules\Ai\Interfaces\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\Agent\Data\AgentBlueprint;
use Illuminate\Http\JsonResponse;

/**
 * Read-only listing over every agent with a real system prompt:
 * `config('ai.agent.registry')` (conversation-routable agents) plus
 * `config('ai.agent.specialists')` (handoff-only specialists — see that
 * config key's docblock for why they're tracked separately from
 * `registry` but still shown here). Each entry's `system_prompt` is
 * whatever `$class::blueprint()` declares in code — this endpoint does
 * not resolve `PromptRegistryInterface` overrides itself, it only reports
 * `has_prompt_override` (a `prompt_templates` row with a published
 * version exists for this agent's key) so the canvas can show which
 * agents have an active override without duplicating
 * AiServiceProvider::resolveBlueprintSystemPrompt()'s resolution logic.
 */
class AgentListController extends Controller
{
    public function index(): JsonResponse
    {
        $agents = [...config('ai.agent.registry', []), ...config('ai.agent.specialists', [])];

        $overriddenKeys = PromptTemplate::query()
            ->whereNotNull('active_version_id')
            ->whereIn('key', array_map(fn (string $agentType) => "agent_{$agentType}_system_prompt", array_keys($agents)))
            ->pluck('key')
            ->all();

        $data = array_map(function (string $agentType, string $class) use ($overriddenKeys) {
            /** @var AgentBlueprint $blueprint */
            $blueprint = $class::blueprint();

            return [
                'agent_type' => $agentType,
                'service_class' => $class,
                'system_prompt' => $blueprint->systemPrompt,
                'max_iterations' => $blueprint->maxIterations,
                'allowed_side_effects' => $blueprint->allowedSideEffects,
                'tools' => $blueprint->tools,
                'has_prompt_override' => in_array("agent_{$agentType}_system_prompt", $overriddenKeys, true),
            ];
        }, array_keys($agents), array_values($agents));

        return response()->json(['data' => $data]);
    }
}
