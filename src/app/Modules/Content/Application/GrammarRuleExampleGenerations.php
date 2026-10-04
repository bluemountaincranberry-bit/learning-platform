<?php

namespace App\Modules\Content\Application;

use App\Contracts\Ai\GrammarRuleExampleGenerationDispatcher;
use App\Modules\Content\Application\Contracts\GrammarRuleExampleGenerationsInterface;
use App\Modules\Content\Application\Data\GrammarRuleExampleGenerationRequest;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExampleGeneration;
use App\Support\AiConfig;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class GrammarRuleExampleGenerations implements GrammarRuleExampleGenerationsInterface
{
    public function __construct(private readonly GrammarRuleExampleGenerationDispatcher $dispatcher) {}

    public function request(int $ruleId, ?int $userId, int $size, ?string $translationLanguage): GrammarRuleExampleGenerationRequest
    {
        if (! AiConfig::isEnabled()) {
            return new GrammarRuleExampleGenerationRequest(GrammarRuleExampleGenerationRequest::UNAVAILABLE);
        }

        // Two taps at the same moment must not both pass the "no active batch" check.
        try {
            $generationId = Cache::lock('grammar-rule-example-generation:'.$ruleId, 10)->block(5, function () use ($ruleId, $userId, $size, $translationLanguage): int|string {
                if (GrammarRuleExampleGeneration::query()->where('grammar_rule_id', $ruleId)->active()->exists()) {
                    return GrammarRuleExampleGenerationRequest::ACTIVE;
                }

                if ($userId !== null && $this->usedToday($ruleId, $userId) >= (int) config('ai.examples.daily_batches_per_rule', 3)) {
                    return GrammarRuleExampleGenerationRequest::LIMITED;
                }

                return GrammarRuleExampleGeneration::query()->create([
                    'grammar_rule_id' => $ruleId,
                    'user_id' => $userId,
                    'size' => $size,
                    'translation_language' => $translationLanguage,
                    'status' => GrammarRuleExampleGeneration::STATUS_QUEUED,
                ])->id;
            });
        } catch (LockTimeoutException) {
            // Another request for this rule holds the lock and is creating the batch.
            return new GrammarRuleExampleGenerationRequest(GrammarRuleExampleGenerationRequest::ACTIVE);
        }

        if (is_string($generationId)) {
            return new GrammarRuleExampleGenerationRequest($generationId);
        }

        $this->dispatcher->dispatch($generationId);

        return new GrammarRuleExampleGenerationRequest(GrammarRuleExampleGenerationRequest::QUEUED);
    }

    public function latestStatus(int $ruleId): string
    {
        $latest = GrammarRuleExampleGeneration::query()
            ->where('grammar_rule_id', $ruleId)
            ->latest('id')
            ->first(['status', 'created_at']);

        if ($latest === null) {
            return 'idle';
        }

        // A batch whose worker died must not keep the page spinning.
        if (in_array($latest->status, GrammarRuleExampleGeneration::ACTIVE_STATUSES, true)
            && $latest->created_at->lt(now()->subMinutes(GrammarRuleExampleGeneration::STALE_AFTER_MINUTES))) {
            return GrammarRuleExampleGeneration::STATUS_FAILED;
        }

        return $latest->status;
    }

    public function start(int $generationId): ?array
    {
        $generation = GrammarRuleExampleGeneration::query()->find($generationId);
        if ($generation === null || ! in_array($generation->status, GrammarRuleExampleGeneration::ACTIVE_STATUSES, true)) {
            return null;
        }

        $generation->update(['status' => GrammarRuleExampleGeneration::STATUS_RUNNING, 'started_at' => now()]);

        return [
            'rule_id' => $generation->grammar_rule_id,
            'size' => $generation->size,
            'translation_language' => $generation->translation_language,
        ];
    }

    public function finish(int $generationId, int $createdCount): void
    {
        GrammarRuleExampleGeneration::query()->whereKey($generationId)->update([
            'status' => GrammarRuleExampleGeneration::STATUS_DONE,
            'created_count' => $createdCount,
            'finished_at' => now(),
        ]);
    }

    public function fail(int $generationId, string $error): void
    {
        GrammarRuleExampleGeneration::query()->whereKey($generationId)->update([
            'status' => GrammarRuleExampleGeneration::STATUS_FAILED,
            'error' => Str::limit($error, 250),
            'finished_at' => now(),
        ]);
    }

    public function rulesBelow(int $minExamples, array $onlyRuleIds = []): array
    {
        return GrammarRule::query()
            ->where('status', '!=', GrammarRule::STATUS_ARCHIVED)
            ->when($onlyRuleIds !== [], fn ($query) => $query->whereIn('id', $onlyRuleIds))
            ->withCount('examples')
            ->orderBy('id')
            ->get(['id'])
            ->filter(fn (GrammarRule $rule): bool => (int) $rule->examples_count < $minExamples)
            ->mapWithKeys(fn (GrammarRule $rule): array => [$rule->id => (int) $rule->examples_count])
            ->all();
    }

    private function usedToday(int $ruleId, int $userId): int
    {
        return GrammarRuleExampleGeneration::query()
            ->where('user_id', $userId)
            ->where('grammar_rule_id', $ruleId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }
}
