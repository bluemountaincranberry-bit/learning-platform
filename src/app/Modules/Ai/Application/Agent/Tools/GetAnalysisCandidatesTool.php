<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\CandidateAnalysisStoreInterface;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;

/**
 * Read-only: lets the agent summarize what the latest analysis run found,
 * so it can tell the admin "12 words and 2 grammar points, want me to open
 * it for review?" instead of guessing.
 */
class GetAnalysisCandidatesTool implements AgentTool
{
    public function __construct(
        private readonly ContentAnalysisSourceReaderInterface $contentSource,
        private readonly CandidateAnalysisStoreInterface $candidateStore,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'get_analysis_candidates',
            description: 'Lists the vocabulary and grammar candidates found by the latest analysis run for a content.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'content_id' => ['type' => 'integer'],
                ],
                'required' => ['content_id'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_READ_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $contentId = (int) ($arguments['content_id'] ?? 0);
        if ($this->contentSource->hasSourceText($contentId) === null) {
            throw new AgentToolException('No content found with that content_id.');
        }

        $run = AiAnalysisRun::query()->where('content_id', $contentId)->orderByDesc('id')->first();
        if (! $run) {
            return ['lexemes' => [], 'grammar' => [], 'note' => 'No analysis run yet.'];
        }

        return [
            'run_id' => $run->id,
            'run_status' => $run->status,
            ...$this->candidateStore->listingForRun($run->id),
        ];
    }
}
