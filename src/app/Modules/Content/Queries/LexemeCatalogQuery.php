<?php

namespace App\Modules\Content\Queries;

use App\Modules\Content\Application\Support\GrammarCoverageStateResolver;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class LexemeCatalogQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Lexeme::query()->withCount(['examples', 'rules', 'contentLinks', 'associations']);

        foreach (['language', 'status', 'level', 'part_of_speech'] as $field) {
            if (($filters[$field] ?? null) !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        if (($filters['rule_id'] ?? null) !== null) {
            $query->whereHas('rules', fn (Builder $ruleQuery) => $ruleQuery->where('grammar_rules.id', $filters['rule_id']));
        }
        if (($filters['content_id'] ?? null) !== null) {
            $query->whereHas('contentLinks', fn (Builder $contentQuery) => $contentQuery->where('content_id', $filters['content_id']));
        }
        if (($filters['linked_content'] ?? null) !== null) {
            $filters['linked_content'] ? $query->has('contentLinks') : $query->doesntHave('contentLinks');
        }
        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('lemma', 'like', "%{$search}%")
                    ->orWhere('normalized_lemma', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        if (($coverageState = $filters['coverage_state'] ?? null) !== null) {
            match ($coverageState) {
                'needs_examples' => $query->doesntHave('examples'),
                'needs_rules' => $query->has('examples')->doesntHave('rules'),
                'needs_content' => $query->has('examples')->has('rules')->doesntHave('contentLinks'),
                'covered' => $query->has('examples')->has('rules')->has('contentLinks'),
                default => null,
            };
        }

        $paginator = $query->orderBy('lemma')->paginate((int) ($filters['per_page'] ?? 15));
        $paginator->getCollection()->transform(fn (Lexeme $lexeme): Lexeme => $this->withCoverageState($lexeme));

        return $paginator;
    }

    public function detail(Lexeme $lexeme): Lexeme
    {
        $lexeme->load([
            'rules:id,topic_id,slug,title,status,level',
            'examples:id,lexeme_id,language,example,translation,is_primary,sort_order',
            'associations.relatedLexeme:id,slug,lemma,normalized_lemma',
        ])->loadCount(['examples', 'rules', 'contentLinks', 'associations']);

        return $this->withCoverageState($lexeme);
    }

    private function withCoverageState(Lexeme $lexeme): Lexeme
    {
        $lexeme->setAttribute('coverage_state', GrammarCoverageStateResolver::forLexeme(
            (int) $lexeme->examples_count,
            (int) $lexeme->rules_count,
            (int) $lexeme->content_links_count,
        ));

        return $lexeme;
    }
}
