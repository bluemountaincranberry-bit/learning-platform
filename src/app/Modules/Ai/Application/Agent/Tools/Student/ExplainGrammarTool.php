<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\RagRetrievalService;
use App\Modules\Content\Application\Contracts\GrammarExplanationReaderInterface;

/**
 * Learning tool (task 3.4): retrieves a matching `GrammarRule` so the model
 * can ground its explanation of a known construction ("what is Present
 * Perfect") in curated content instead of inventing one.
 *
 * Task 2.5: retrieval is now `RagRetrievalService` (embed query -> ES kNN
 * over the `grammar_rule` doc_type, task 2.4) instead of the original plain
 * SQL `LIKE` match — the `AgentTool` interface and this class's public
 * shape (`definition()`/`execute()` signature, return keys) are unchanged,
 * exactly as planned when this tool was first written. The best-matching
 * hit's `source_id` is then loaded from Postgres to hydrate `examples`
 * (still `grammar_rule_examples`, not part of the RAG corpus itself) and to
 * confirm the rule is still published — a stale/unpublished index entry
 * degrades to "not found" rather than leaking draft content.
 *
 * This is the Tool side of the Tool/Agent boundary from ADR-002 and
 * ai-platform-vision.md section 4: explaining an already-known construction
 * is one grounded completion, not a multi-step `GrammarAgent` (EPIC 4) that
 * diagnoses the student's own sentence.
 */
class ExplainGrammarTool implements AgentTool
{
    public function __construct(
        private readonly RagRetrievalService $rag,
        private readonly GrammarExplanationReaderInterface $rules,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'explain_grammar',
            description: 'Looks up a grammar rule matching a topic (e.g. "present perfect", "conditionals") from the curated catalog, returning its summary/body/examples to explain to the student.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'description' => 'The grammar topic to look up, e.g. "present perfect".',
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en".',
                    ],
                ],
                'required' => ['topic'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $topic = trim((string) ($arguments['topic'] ?? ''));
        if ($topic === '') {
            return ['error' => 'topic is required.'];
        }

        $language = isset($arguments['language']) ? strtolower(trim((string) $arguments['language'])) : null;

        $documents = $this->rag->retrieve($topic, docType: 'grammar_rule', language: $language, topK: 3);

        if ($documents === []) {
            return [
                'found' => false,
                'note' => "No published grammar rule matched \"{$topic}\".",
            ];
        }

        $best = $documents[0];

        $rule = $this->rules->publishedRule((int) $best['source_id']);

        if ($rule === null) {
            // Indexed but no longer published/deleted since — degrade to
            // "not found" instead of serving stale/unpublished content.
            return [
                'found' => false,
                'note' => "No published grammar rule matched \"{$topic}\".",
            ];
        }

        return [
            'found' => true,
            'title' => $rule->title,
            'level' => $rule->level,
            'summary' => $rule->summary,
            'body' => $this->rag->wrapAsToolOutput([$best]),
            'examples' => $rule->examples,
            'match_score' => $best['score'],
        ];
    }
}
