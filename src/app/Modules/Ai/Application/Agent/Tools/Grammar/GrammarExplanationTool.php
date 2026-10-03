<?php

namespace App\Modules\Ai\Application\Agent\Tools\Grammar;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Content\Application\Contracts\GrammarExplanationReaderInterface;

/**
 * GrammarAgent tool 2/3 (task 4.4): hydrates one specific grammar rule
 * (chosen by the agent from `GrammarSearchTool` candidates, or already
 * known by id) with its full summary/body/examples — a plain, read-only
 * Postgres lookup, no LLM call needed here; the agent's own reasoning is
 * what composes the final tailored explanation from this grounded data.
 * Only ever returns published rules, matching `ExplainGrammarTool`'s same
 * "stale/unpublished index entry degrades to not found" rule.
 */
class GrammarExplanationTool implements AgentTool
{
    public function __construct(private readonly GrammarExplanationReaderInterface $rules) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'grammar_explanation_lookup',
            description: 'Fetches the full explanation (summary, body, examples) for one specific published grammar rule by id, once you know which rule is actually relevant.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'grammar_rule_id' => [
                        'type' => 'integer',
                        'description' => 'The id of the grammar rule to fetch, e.g. from grammar_search results.',
                    ],
                ],
                'required' => ['grammar_rule_id'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $ruleId = $arguments['grammar_rule_id'] ?? null;

        if (! is_int($ruleId) && ! (is_string($ruleId) && ctype_digit($ruleId))) {
            return ['error' => 'grammar_rule_id must be an integer.'];
        }

        $rule = $this->rules->publishedRule((int) $ruleId);

        if ($rule === null) {
            return [
                'found' => false,
                'note' => "No published grammar rule found for id {$ruleId}.",
            ];
        }

        return [
            'found' => true,
            'title' => $rule->title,
            'level' => $rule->level,
            'summary' => $rule->summary,
            // Same <tool_output> boundary as ExtractPdfTextTool/RagRetrievalService
            // (task 1.8/5.9): body can originate from the AI content-analysis
            // pipeline, not just an admin's own writing — treat it as data,
            // never as instructions.
            'body' => "<tool_output>\n{$rule->body}\n</tool_output>",
            'examples' => $rule->examples,
        ];
    }
}
