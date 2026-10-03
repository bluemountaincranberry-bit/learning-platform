<?php

namespace App\Modules\Ai\Application\Agent\Tools\Handoff;

use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\HandoffTool;

/**
 * Wires `StudentTutorAgentService` to `GrammarAgentService` via
 * model-directed handoff (task 4.3/4.4) — this is what actually closes the
 * loop the epic's Definition of Done describes ("TutorAgent умеет
 * передавать управление GrammarAgent... по делу"): `HandoffTool` alone is
 * just the mechanism, this concrete subclass plus being added to
 * `StudentTutorAgentService::blueprint()->tools` is what makes it
 * reachable.
 *
 * `sideEffect = read_only` — `GrammarAgentService`'s widest allowed
 * sideEffect is also `read_only` (it never proposes drafts), so this is
 * the minimum that satisfies `HandoffTool`'s wiring-time coverage check.
 *
 * Model-directed (the student's free-text question is what decides this
 * is a grammar question), distinct from `TutorRoutingGraph`'s
 * deterministic routing (task 4.7, for an already-explicit signal like a
 * UI button) — see `ai-platform-vision.md` section 6's "two modes".
 */
final class HandoffToGrammarAgentTool extends HandoffTool
{
    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'handoff_to_grammar_specialist',
            description: 'Hands off to a grammar specialist to diagnose a specific mistake in the student\'s own sentence, or to give a deeper explanation than a simple lookup — use for "what\'s wrong with this sentence" style requests, not for "what is X" (use explain_grammar for that instead).',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'task' => [
                        'type' => 'string',
                        'description' => 'The student\'s sentence/question to hand off, in enough detail for the specialist to work from without seeing the rest of the conversation.',
                    ],
                ],
                'required' => ['task'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }
}
