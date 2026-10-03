<?php

namespace App\Modules\Ai\Application\Agent\Tools\Student;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Content\Application\Contracts\LexemeCatalogSearchInterface;

/**
 * Learning tool (task 3.4): searches the published vocabulary catalog by
 * lemma text. Plain SQL `LIKE` for now, deliberately — EPIC 2 upgrades
 * `ExplainGrammarTool`/`FindExamplesTool` to Elasticsearch RAG later without
 * changing the `AgentTool` interface (see docs/architecture/
 * ai-platform-implementation-roadmap.md, task 2.5); this tool stays SQL for
 * the whole MVP since the catalog is a lookup table, not free text.
 */
class SearchVocabularyTool implements AgentTool
{
    public function __construct(private readonly LexemeCatalogSearchInterface $catalog) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'search_vocabulary',
            description: 'Searches the published vocabulary catalog by word/phrase text, to check whether a word exists, look up its level, or list related forms.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Text to search for, e.g. "run" or "give up".',
                    ],
                    'language' => [
                        'type' => 'string',
                        'description' => 'Optional ISO 639-1 language code to scope to, e.g. "en".',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results (default 10, max 30).',
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
        $limit = min(30, max(1, (int) ($arguments['limit'] ?? 10)));

        $results = $this->catalog->searchPublished($query, $language, $limit);

        return [
            'result_count' => count($results),
            'results' => $results,
        ];
    }
}
