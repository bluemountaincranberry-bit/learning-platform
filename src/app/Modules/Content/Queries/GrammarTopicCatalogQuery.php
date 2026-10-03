<?php

namespace App\Modules\Content\Queries;

use App\Modules\Content\Application\Support\GrammarCoverageStateResolver;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class GrammarTopicCatalogQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = GrammarTopic::query()
            ->withCount('rules')
            ->withCount([
                'rules as covered_rules_count' => fn (Builder $builder) => $builder
                    ->has('examples')
                    ->has('lexemes')
                    ->has('contentLinks'),
            ]);

        if ($language = $filters['language'] ?? null) {
            $query->where('language', $language);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($coverageState = $filters['coverage_state'] ?? null) {
            $this->applyCoverageFilter($query, $coverageState);
        }

        $paginator = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $paginator->getCollection()->transform(fn (GrammarTopic $topic): GrammarTopic => $this->withCoverageState($topic));

        return $paginator;
    }

    public function detail(GrammarTopic $topic): GrammarTopic
    {
        $topic->loadCount('rules')->loadCount([
            'rules as covered_rules_count' => fn (Builder $builder) => $builder
                ->has('examples')
                ->has('lexemes')
                ->has('contentLinks'),
        ]);

        return $this->withCoverageState($topic);
    }

    private function withCoverageState(GrammarTopic $topic): GrammarTopic
    {
        $topic->setAttribute('coverage_state', GrammarCoverageStateResolver::forTopic(
            (int) $topic->rules_count,
            (int) $topic->covered_rules_count,
        ));

        return $topic;
    }

    private function applyCoverageFilter(Builder $query, string $coverageState): void
    {
        match ($coverageState) {
            'empty' => $query->doesntHave('rules'),
            'covered' => $query->has('rules')->whereDoesntHave('rules', fn (Builder $ruleQuery) => $this->applyUncoveredRuleFilter($ruleQuery)),
            'needs_coverage' => $query->where(function (Builder $topicQuery): void {
                $topicQuery->doesntHave('rules')
                    ->orWhereHas('rules', fn (Builder $ruleQuery) => $this->applyUncoveredRuleFilter($ruleQuery));
            }),
            default => null,
        };
    }

    private function applyUncoveredRuleFilter(Builder $query): void
    {
        $query->where(function (Builder $ruleQuery): void {
            $ruleQuery->doesntHave('examples')
                ->orWhere(fn (Builder $incompleteRule) => $incompleteRule->has('examples')->doesntHave('lexemes'))
                ->orWhere(fn (Builder $incompleteRule) => $incompleteRule->has('examples')->has('lexemes')->doesntHave('contentLinks'));
        });
    }
}
