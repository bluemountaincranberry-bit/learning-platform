<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarProgressServiceInterface;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Application\Contracts\GrammarProgressStoreInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GrammarProgressService implements GrammarProgressServiceInterface
{
    public function __construct(
        private GrammarProgressStoreInterface $progressStore,
    ) {}

    public function startLearningById(int $grammarRuleId, int $userId): void
    {
        $rule = GrammarRule::query()->find($grammarRuleId);
        if ($rule !== null) {
            $this->startLearning($rule, $userId);
        }
    }

    public function startLearning(GrammarRule $rule, int $userId): void
    {
        $this->progressStore->startLearning($userId, (int) $rule->id);
    }

    public function markLearned(GrammarRule $rule, int $userId): void
    {
        $this->progressStore->markLearned($userId, (int) $rule->id);
    }

    public function unmarkLearned(GrammarRule $rule, int $userId): void
    {
        $this->progressStore->remove($userId, (int) $rule->id);
    }

    public function setManualConfidence(GrammarRule $rule, int $userId, ?float $confidence): void
    {
        $this->progressStore->setManualConfidence($userId, (int) $rule->id, $confidence);
    }

    public function getPaginated(int $userId, array $filters = []): LengthAwarePaginator
    {
        $paginator = $this->progressStore->getPaginated($userId, $filters);
        $progressRows = $paginator->getCollection();
        $rules = GrammarRule::query()
            ->with('topic:id,name')
            ->whereIn('id', $progressRows->pluck('grammar_rule_id'))
            ->get()
            ->keyBy('id');

        foreach ($progressRows as $progress) {
            $rule = $rules->get($progress->grammar_rule_id);
            $progress->rule_title = $rule?->title;
            $progress->rule_summary = $rule?->summary;
            $progress->rule_level = $rule?->level;
            $progress->rule_topic_id = $rule?->topic?->id;
            $progress->rule_topic_name = $rule?->topic?->name;
        }

        return $paginator;
    }

    public function getFlagsForUser(int $userId): array
    {
        return $this->progressStore->flags($userId);
    }
}
