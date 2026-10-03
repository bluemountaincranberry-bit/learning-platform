<?php

namespace App\Modules\Ai\Application\Agent\Tools\Grammar;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\RagRetrievalService;

/**
 * GrammarAgent tool 1/3 (task 4.4): returns several *candidate* matching
 * grammar rules (title/summary/id/score) for a topic or a student's own
 * sentence, deliberately without the full body/examples —
 * `GrammarExplanationTool` fetches those once the agent (or, upstream, the
 * model) has picked which specific rule is actually relevant. Splitting
 * "search" from "explain" into two tools is what gives GrammarAgent an
 * actual multi-step loop to run (search -> analyze the student's error
 * against a candidate -> explain the one that matches) — the same
 * search/fetch split `RagRetrievalService` already supports, just used
 * here as two separate tool calls instead of one (contrast with
 * `ExplainGrammarTool`, which collapses both into a single Tool call
 * because it never needs to weigh candidates against a student's own
 * mistake — see that class's docblock and ADR-002).
 */
class GrammarSearchTool implements AgentTool
{
    public function __construct(
        private readonly RagRetrievalService $rag,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'grammar_search',
            description: 'Searches the curated grammar catalog for rules that might explain a topic or a student mistake. Returns candidate matches (title, summary, id, score) — use grammar_explanation_lookup to get the full explanation for the one that actually fits.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'A grammar topic, or the student\'s own sentence/mistake to search against.',
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en".',
                    ],
                ],
                'required' => ['query'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return ['error' => 'query is required.'];
        }

        $language = isset($arguments['language']) ? strtolower(trim((string) $arguments['language'])) : null;

        $documents = $this->rag->retrieve($query, docType: 'grammar_rule', language: $language, topK: 5);

        if ($documents === []) {
            return [
                'candidates' => [],
                'note' => "No grammar rule candidates matched \"{$query}\".",
            ];
        }

        return [
            'candidates' => array_map(fn (array $doc) => [
                'grammar_rule_id' => $doc['source_id'],
                'title' => $doc['title'] ?? null,
                'summary' => $doc['summary'] ?? null,
                'score' => $doc['score'],
            ], $documents),
        ];
    }
}
