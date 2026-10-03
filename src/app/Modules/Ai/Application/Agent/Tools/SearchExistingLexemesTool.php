<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Content\Application\Contracts\LexemeCatalogSearchInterface;

/**
 * Read-only catalog lookup so the agent can check "do we already have this
 * word?" before proposing it again, and answer admin questions about the
 * existing catalog without needing a tool that can write to it.
 */
class SearchExistingLexemesTool implements AgentTool
{
    public function __construct(private readonly LexemeCatalogSearchInterface $catalog) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'search_existing_lexemes',
            description: 'Searches the already-published lexeme catalog by language and text, to check for duplicates or answer questions about existing content.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'language' => ['type' => 'string'],
                    'query' => ['type' => 'string'],
                ],
                'required' => ['language', 'query'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $language = strtolower(trim((string) ($arguments['language'] ?? '')));
        $query = trim((string) ($arguments['query'] ?? ''));

        return ['results' => $this->catalog->search($language, $query, 20)];
    }
}
