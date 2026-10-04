<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\AiErrorMessage;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiGrammarRuleExampleService;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleGenerationsInterface;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One AI batch of example sentences for a grammar rule (VIK-39). The batch
 * row in grammar_rule_example_generations tracks status for the rule page;
 * failures mark it failed so "More examples" can be tapped again instead
 * of waiting forever. No retries: a retry would be another paid call the
 * learner did not ask for. One batch per rule at a time is enforced when
 * the batch row is created (GrammarRuleExampleGenerations), not here.
 */
class GenerateGrammarRuleExamplesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $generationId) {}

    public function tags(): array
    {
        return ['grammar-rule-example-generation:'.$this->generationId, 'job:generate-grammar-rule-examples'];
    }

    public function handle(GrammarRuleExampleGenerationsInterface $generations, AiGrammarRuleExampleService $service): void
    {
        $batch = $generations->start($this->generationId);
        if ($batch === null) {
            return;
        }

        if (! AiConfig::isEnabled()) {
            $generations->fail($this->generationId, 'AI is disabled.');

            return;
        }

        try {
            $created = $service->generate($batch['rule_id'], $batch['size'], $batch['translation_language']);
        } catch (AiClientException $e) {
            Log::warning('GenerateGrammarRuleExamplesJob: AI client failed', [
                'generation_id' => $this->generationId,
                'message' => AiErrorMessage::safe($e),
            ]);
            $generations->fail($this->generationId, AiErrorMessage::safe($e));

            return;
        }

        $generations->finish($this->generationId, $created);
    }

    public function failed(?Throwable $e): void
    {
        app(GrammarRuleExampleGenerationsInterface::class)->fail($this->generationId, $e?->getMessage() ?? 'Job failed.');
    }
}
