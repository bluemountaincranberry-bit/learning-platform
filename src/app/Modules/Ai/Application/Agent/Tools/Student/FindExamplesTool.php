<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\RagRetrievalService;

/**
 * Learning tool (task 3.4): finds curated example sentences for a word or
 * phrase from `lexeme_examples`.
 *
 * Task 2.5: retrieval is now `RagRetrievalService` (embed query -> ES kNN
 * over the `lexeme_example` doc_type, task 2.4) instead of the original
 * plain SQL `LIKE` match on the lemma — the `AgentTool` interface is
 * unchanged. One behavioral difference worth being explicit about: the
 * original tool matched a single lexeme (by lemma) and returned all of its
 * examples; semantic search instead returns the top-K *examples* by
 * meaning, which may span more than one lexeme when they're closely
 * related (e.g. "give up" and "quit"). `lemma` in the response reflects the
 * best match only, `examples` can mix lemmas — documented here rather than
 * forcing a single-lexeme grouping that would fight the point of semantic
 * retrieval.
 */
class FindExamplesTool implements AgentTool
{
    public function __construct(
        private readonly RagRetrievalService $rag
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'find_examples',
            description: 'Finds curated example sentences for a word or phrase from the vocabulary catalog.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'word' => [
                        'type' => 'string',
                        'description' => 'The word or phrase to find examples for, e.g. "give up".',
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en".',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of examples (default 3, max 10).',
                    ],
                ],
                'required' => ['word'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $word = trim((string) ($arguments['word'] ?? ''));
        if ($word === '') {
            return ['error' => 'word is required.'];
        }

        $language = isset($arguments['language']) ? strtolower(trim((string) $arguments['language'])) : null;
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 3)));

        $documents = $this->rag->retrieve($word, docType: 'lexeme_example', language: $language, topK: $limit);

        if ($documents === []) {
            return [
                'found' => false,
                'examples' => [],
                'note' => "No curated examples found for \"{$word}\".",
            ];
        }

        return [
            'found' => true,
            'lemma' => $documents[0]['lemma'] ?? null,
            'examples' => array_map(fn (array $doc) => [
                'example' => $doc['example'] ?? $doc['text'],
                'translation' => $doc['translation'] ?? null,
                'lemma' => $doc['lemma'] ?? null,
            ], $documents),
            'context' => $this->rag->wrapAsToolOutput($documents),
        ];
    }
}
