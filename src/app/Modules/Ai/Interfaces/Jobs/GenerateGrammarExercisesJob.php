<?php

namespace App\Modules\Ai\Interfaces\Jobs;

use App\Contracts\Ai\AiErrorMessage;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiGrammarExerciseService;
use App\Modules\Content\Application\Contracts\GrammarExerciseGenerationsInterface;
use App\Support\AiConfig;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * One learner-triggered AI batch of grammar exercises (VIK-31). The batch
 * row in grammar_exercise_generations tracks status for the round screen;
 * failures mark it failed so the learner sees "can't be prepared" instead of
 * waiting forever. No retries: the learner can tap Try again.
 */
class GenerateGrammarExercisesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public int $generationId) {}

    public function tags(): array
    {
        return ['grammar-exercise-generation:'.$this->generationId, 'job:generate-grammar-exercises'];
    }

    public function uniqueId(): string
    {
        return 'GenerateGrammarExercises:'.$this->generationId;
    }

    public function handle(GrammarExerciseGenerationsInterface $generations, AiGrammarExerciseService $service): void
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
            $created = $service->generate($batch['rule_id'], $batch['size'], null, 'ai');
        } catch (AiClientException $e) {
            Log::warning('GenerateGrammarExercisesJob: AI client failed', [
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
        app(GrammarExerciseGenerationsInterface::class)->fail($this->generationId, $e?->getMessage() ?? 'Job failed.');
    }
}
