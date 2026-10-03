<?php

namespace App\Modules\Ai\Application\Agent\Tools;

use App\Exceptions\AgentToolException;
use App\Modules\Ai\Application\Agent\Contracts\AgentTool;
use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\CandidateMatchingService;
use App\Contracts\Ai\AiAnalysisRunConfig;
use App\Contracts\Ai\ContentAnalysisCapability;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Application\Contracts\ContentAnalysisSourceReaderInterface;
use App\Modules\Content\Application\Data\LexemeMetadataOptions;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the same AI candidate-extraction pipeline as the "Analyze with AI"
 * button on the Content admin page (AiContentAnalysisService +
 * CandidateMatchingService), but executed inline instead of dispatching
 * RunAiContentAnalysisJob — the whole agent turn already runs inside a
 * queued job (see RunAgentTurnJob), so there is no web request to keep
 * short, and running it inline lets the agent report real candidate counts
 * back to the admin in the same reply instead of a second, disconnected turn.
 */
class RunContentAnalysisTool implements AgentTool
{
    public function __construct(
        private readonly ContentAnalysisCapability $analysisService,
        private readonly CandidateMatchingService $matchingService,
        private readonly ContentAnalysisSourceReaderInterface $contentSources,
    ) {}

    public function definition(): AgentToolDefinition
    {
        return new AgentToolDefinition(
            name: 'run_content_analysis',
            description: 'Extracts vocabulary/phrase and grammar candidates from a draft lesson\'s text for admin review. Call this after create_content.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'content_id' => ['type' => 'integer'],
                    'translation_language' => ['type' => 'string', 'description' => 'Language to translate words/examples into, e.g. "ru".'],
                    'target_level' => ['type' => 'string', 'enum' => LexemeMetadataOptions::cefrLevels()],
                    'thoroughness' => ['type' => 'string', 'enum' => AiAnalysisRunConfig::THOROUGHNESS_LEVELS],
                ],
                'required' => ['content_id'],
            ],
            sideEffect: AgentToolDefinition::SIDE_EFFECT_DRAFT_ONLY,
        );
    }

    public function execute(array $arguments, AgentToolContext $context): array
    {
        $contentId = (int) ($arguments['content_id'] ?? 0);
        $hasSourceText = $this->contentSources->hasSourceText($contentId);
        if ($hasSourceText === null) {
            throw new AgentToolException('No content found with that content_id.');
        }
        if (! $hasSourceText) {
            throw new AgentToolException('This content has no source_text to analyze yet.');
        }

        $config = AiAnalysisRunConfig::fromArray($arguments);

        $run = AiAnalysisRun::query()->create([
            'content_id' => $contentId,
            'status' => AiAnalysisRun::STATUS_PENDING,
            'config' => $config->toArray(),
        ]);

        $run->update(['status' => AiAnalysisRun::STATUS_RUNNING, 'started_at' => now()]);

        try {
            $this->analysisService->analyze($run);

            try {
                $this->matchingService->matchRun($run);
            } catch (Throwable $e) {
                Log::warning('CandidateMatchingService failed for agent-triggered analysis run', [
                    'run_id' => $run->id,
                    'message' => $e->getMessage(),
                ]);
            }

            $run->update(['status' => AiAnalysisRun::STATUS_COMPLETED, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $run->update([
                'status' => AiAnalysisRun::STATUS_FAILED,
                'completed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);

            throw new AgentToolException('Analysis failed: '.$e->getMessage());
        }

        return [
            'run_id' => $run->id,
            'lexeme_candidate_count' => $run->lexemeCandidates()->count(),
            'grammar_candidate_count' => $run->grammarCandidates()->count(),
        ];
    }
}
