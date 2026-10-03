<?php

namespace App\Modules\Content\Application;

use App\Contracts\Ai\GrammarExerciseGenerationDispatcher;
use App\Modules\Content\Application\Contracts\GrammarExerciseGenerationsInterface;
use App\Modules\Content\Application\Data\GrammarExerciseGenerationRequest;
use App\Modules\Content\Domain\Models\GrammarExerciseGeneration;
use App\Support\AiConfig;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class GrammarExerciseGenerations implements GrammarExerciseGenerationsInterface
{
    public function __construct(private readonly GrammarExerciseGenerationDispatcher $dispatcher) {}

    public function request(int $ruleId, int $userId, int $size): GrammarExerciseGenerationRequest
    {
        if (! AiConfig::isEnabled()) {
            return new GrammarExerciseGenerationRequest(GrammarExerciseGenerationRequest::UNAVAILABLE);
        }

        // Two taps (or a tap and a top-up) at the same moment must not both
        // pass the "no active batch" check.
        try {
            $generationId = Cache::lock('grammar-exercise-generation:'.$ruleId, 10)->block(5, function () use ($ruleId, $userId, $size): int|string {
                if ($this->hasActive($ruleId)) {
                    return GrammarExerciseGenerationRequest::ACTIVE;
                }

                $usedToday = GrammarExerciseGeneration::query()
                    ->where('user_id', $userId)
                    ->where('grammar_rule_id', $ruleId)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->count();

                if ($usedToday >= (int) config('ai.exercises.practice.daily_batches_per_rule', 3)) {
                    return GrammarExerciseGenerationRequest::LIMITED;
                }

                return GrammarExerciseGeneration::query()->create([
                    'grammar_rule_id' => $ruleId,
                    'user_id' => $userId,
                    'size' => $size,
                    'status' => GrammarExerciseGeneration::STATUS_QUEUED,
                ])->id;
            });
        } catch (LockTimeoutException) {
            // Another request for this rule holds the lock and is creating the batch.
            return new GrammarExerciseGenerationRequest(GrammarExerciseGenerationRequest::ACTIVE);
        }

        if (is_string($generationId)) {
            return new GrammarExerciseGenerationRequest($generationId);
        }

        $this->dispatcher->dispatch($generationId);

        return new GrammarExerciseGenerationRequest(GrammarExerciseGenerationRequest::QUEUED);
    }

    public function hasActive(int $ruleId): bool
    {
        return GrammarExerciseGeneration::query()->where('grammar_rule_id', $ruleId)->active()->exists();
    }

    public function start(int $generationId): ?array
    {
        $generation = GrammarExerciseGeneration::query()->find($generationId);
        if ($generation === null || ! in_array($generation->status, GrammarExerciseGeneration::ACTIVE_STATUSES, true)) {
            return null;
        }

        $generation->update(['status' => GrammarExerciseGeneration::STATUS_RUNNING, 'started_at' => now()]);

        return ['rule_id' => $generation->grammar_rule_id, 'size' => $generation->size];
    }

    public function finish(int $generationId, int $createdCount): void
    {
        GrammarExerciseGeneration::query()->whereKey($generationId)->update([
            'status' => GrammarExerciseGeneration::STATUS_DONE,
            'created_count' => $createdCount,
            'finished_at' => now(),
        ]);
    }

    public function fail(int $generationId, string $error): void
    {
        GrammarExerciseGeneration::query()->whereKey($generationId)->update([
            'status' => GrammarExerciseGeneration::STATUS_FAILED,
            'error' => Str::limit($error, 250),
            'finished_at' => now(),
        ]);
    }
}
