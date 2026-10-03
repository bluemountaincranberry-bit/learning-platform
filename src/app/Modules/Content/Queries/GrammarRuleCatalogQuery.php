<?php

namespace App\Modules\Content\Queries;

use App\Modules\Content\Application\Support\GrammarCoverageStateResolver;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class GrammarRuleCatalogQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = GrammarRule::query()
            ->with('topic:id,slug,name')
            ->withCount(['examples', 'lexemes', 'contentLinks']);

        foreach (['topic_id', 'language', 'status', 'level'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['content_id'] ?? null) !== null) {
            $query->whereHas('contentLinks', fn (Builder $contentQuery) => $contentQuery->where('content_id', $filters['content_id']));
        }

        if (($filters['linked_content'] ?? null) !== null) {
            $filters['linked_content'] ? $query->has('contentLinks') : $query->doesntHave('contentLinks');
        }

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($coverageState = $filters['coverage_state'] ?? null) {
            $this->applyCoverageFilter($query, $coverageState);
        }

        $paginator = $query->orderBy('sort_order')->orderBy('title')->paginate((int) ($filters['per_page'] ?? 15));
        $paginator->getCollection()->transform(fn (GrammarRule $rule): GrammarRule => $this->withCoverageState($rule));

        return $paginator;
    }

    public function detail(GrammarRule $rule): GrammarRule
    {
        $rule->load([
            'topic:id,slug,name',
            'lexemes:id,slug,lemma,normalized_lemma,part_of_speech,status,level',
            'examples:id,grammar_rule_id,language,example,translation,is_primary,sort_order',
        ])->loadCount(['examples', 'lexemes', 'contentLinks']);

        return $this->withCoverageState($rule);
    }

    private function withCoverageState(GrammarRule $rule): GrammarRule
    {
        $rule->setAttribute('coverage_state', GrammarCoverageStateResolver::forRule(
            (int) $rule->examples_count,
            (int) $rule->lexemes_count,
            (int) $rule->content_links_count,
        ));

        return $rule;
    }

    private function applyCoverageFilter(Builder $query, string $coverageState): void
    {
        match ($coverageState) {
            'needs_examples' => $query->doesntHave('examples'),
            'needs_lexemes' => $query->has('examples')->doesntHave('lexemes'),
            'needs_content' => $query->has('examples')->has('lexemes')->doesntHave('contentLinks'),
            'covered' => $query->has('examples')->has('lexemes')->has('contentLinks'),
            'uncovered' => $query->where(function (Builder $ruleQuery): void {
                $ruleQuery->doesntHave('examples')
                    ->orWhere(fn (Builder $incompleteRule) => $incompleteRule->has('examples')->doesntHave('lexemes'))
                    ->orWhere(fn (Builder $incompleteRule) => $incompleteRule->has('examples')->has('lexemes')->doesntHave('contentLinks'));
            }),
            default => null,
        };
    }
}
