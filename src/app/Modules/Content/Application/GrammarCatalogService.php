<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface;
use App\Modules\Content\Application\Support\GrammarCoverageStateResolver;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GrammarCatalogService implements GrammarCatalogServiceInterface
{
    public function paginateTopics(array $filters): LengthAwarePaginator
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
            $this->applyTopicCoverageFilter($query, $coverageState);
        }

        $paginator = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $paginator->getCollection()->transform(function (GrammarTopic $topic): GrammarTopic {
            $topic->setAttribute(
                'coverage_state',
                GrammarCoverageStateResolver::forTopic(
                    (int) $topic->rules_count,
                    (int) $topic->covered_rules_count
                )
            );

            return $topic;
        });

        return $paginator;
    }

    public function createTopic(array $data): GrammarTopic
    {
        return GrammarTopic::query()->create([
            'slug' => $this->resolveUniqueSlug(GrammarTopic::class, $data['slug'] ?? null, $data['name']),
            'language' => $data['language'] ?? 'en',
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? GrammarTopic::STATUS_DRAFT,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    public function updateTopic(GrammarTopic $topic, array $data): GrammarTopic
    {
        $topic->fill([
            'slug' => array_key_exists('slug', $data)
                ? $this->resolveUniqueSlug(GrammarTopic::class, $data['slug'], $data['name'] ?? $topic->name, (int) $topic->id)
                : $topic->slug,
            'language' => $data['language'] ?? $topic->language,
            'name' => $data['name'] ?? $topic->name,
            'description' => $data['description'] ?? $topic->description,
            'status' => $data['status'] ?? $topic->status,
            'sort_order' => $data['sort_order'] ?? $topic->sort_order,
        ])->save();

        return $topic->fresh();
    }

    public function deleteTopic(GrammarTopic $topic): void
    {
        $topic->delete();
    }

    public function paginateRules(array $filters): LengthAwarePaginator
    {
        $query = GrammarRule::query()
            ->with('topic:id,slug,name')
            ->withCount(['examples', 'lexemes', 'contentLinks']);

        if ($topicId = $filters['topic_id'] ?? null) {
            $query->where('topic_id', $topicId);
        }

        if ($language = $filters['language'] ?? null) {
            $query->where('language', $language);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($level = $filters['level'] ?? null) {
            $query->where('level', $level);
        }

        if (($filters['content_id'] ?? null) !== null) {
            $query->whereHas('contentLinks', fn (Builder $builder) => $builder->where('content_id', $filters['content_id']));
        }

        if (($filters['linked_content'] ?? null) !== null) {
            ($filters['linked_content'])
                ? $query->has('contentLinks')
                : $query->doesntHave('contentLinks');
        }

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        if ($coverageState = $filters['coverage_state'] ?? null) {
            $this->applyRuleCoverageFilter($query, $coverageState);
        }

        $paginator = $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $paginator->getCollection()->transform(function (GrammarRule $rule): GrammarRule {
            $rule->setAttribute(
                'coverage_state',
                GrammarCoverageStateResolver::forRule(
                    (int) $rule->examples_count,
                    (int) $rule->lexemes_count,
                    (int) $rule->content_links_count
                )
            );

            return $rule;
        });

        return $paginator;
    }

    public function createRule(array $data): GrammarRule
    {
        /** @var GrammarRule $rule */
        $rule = DB::transaction(function () use ($data): GrammarRule {
            $rule = GrammarRule::query()->create([
                'topic_id' => $data['topic_id'],
                'slug' => $this->resolveUniqueSlug(GrammarRule::class, $data['slug'] ?? null, $data['title']),
                'language' => $data['language'] ?? 'en',
                'title' => $data['title'],
                'status' => $data['status'] ?? GrammarRule::STATUS_DRAFT,
                'level' => $data['level'] ?? null,
                'summary' => $data['summary'] ?? null,
                'body' => $data['body'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
            ]);

            $this->syncRuleExamples($rule, $data['examples'] ?? []);
            $this->syncRuleLexemes($rule, $data['lexeme_ids'] ?? []);

            return $rule;
        });

        return $this->getRule($rule);
    }

    public function getRule(GrammarRule $rule): GrammarRule
    {
        $rule->load([
            'topic:id,slug,name',
            'lexemes:id,slug,lemma,normalized_lemma,part_of_speech,status,level',
            'examples:id,grammar_rule_id,language,example,translation,is_primary,sort_order',
        ])->loadCount(['examples', 'lexemes', 'contentLinks']);

        $rule->setAttribute(
            'coverage_state',
            GrammarCoverageStateResolver::forRule(
                (int) $rule->examples_count,
                (int) $rule->lexemes_count,
                (int) $rule->content_links_count
            )
        );

        return $rule;
    }

    public function updateRule(GrammarRule $rule, array $data): GrammarRule
    {
        DB::transaction(function () use ($rule, $data): void {
            $rule->fill([
                'topic_id' => $data['topic_id'] ?? $rule->topic_id,
                'slug' => array_key_exists('slug', $data)
                    ? $this->resolveUniqueSlug(GrammarRule::class, $data['slug'], $data['title'] ?? $rule->title, (int) $rule->id)
                    : $rule->slug,
                'language' => $data['language'] ?? $rule->language,
                'title' => $data['title'] ?? $rule->title,
                'status' => $data['status'] ?? $rule->status,
                'level' => $data['level'] ?? $rule->level,
                'summary' => array_key_exists('summary', $data) ? $data['summary'] : $rule->summary,
                'body' => array_key_exists('body', $data) ? $data['body'] : $rule->body,
                'sort_order' => $data['sort_order'] ?? $rule->sort_order,
            ])->save();

            if (array_key_exists('examples', $data)) {
                $this->syncRuleExamples($rule, $data['examples'] ?? []);
            }

            if (array_key_exists('lexeme_ids', $data)) {
                $this->syncRuleLexemes($rule, $data['lexeme_ids'] ?? []);
            }
        });

        return $this->getRule($rule->fresh());
    }

    public function deleteRule(GrammarRule $rule): void
    {
        $rule->delete();
    }

    public function paginateLexemes(array $filters): LengthAwarePaginator
    {
        $query = Lexeme::query()->withCount([
            'examples',
            'rules',
            'contentLinks',
            'associations',
        ]);

        if ($language = $filters['language'] ?? null) {
            $query->where('language', $language);
        }

        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($level = $filters['level'] ?? null) {
            $query->where('level', $level);
        }

        if ($partOfSpeech = $filters['part_of_speech'] ?? null) {
            $query->where('part_of_speech', $partOfSpeech);
        }

        if (($filters['rule_id'] ?? null) !== null) {
            $query->whereHas('rules', fn (Builder $builder) => $builder->where('grammar_rules.id', $filters['rule_id']));
        }

        if (($filters['content_id'] ?? null) !== null) {
            $query->whereHas('contentLinks', fn (Builder $builder) => $builder->where('content_id', $filters['content_id']));
        }

        if (($filters['linked_content'] ?? null) !== null) {
            ($filters['linked_content'])
                ? $query->has('contentLinks')
                : $query->doesntHave('contentLinks');
        }

        if ($search = $filters['q'] ?? null) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('lemma', 'like', "%{$search}%")
                    ->orWhere('normalized_lemma', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($coverageState = $filters['coverage_state'] ?? null) {
            $this->applyLexemeCoverageFilter($query, $coverageState);
        }

        $paginator = $query
            ->orderBy('lemma')
            ->paginate((int) ($filters['per_page'] ?? 15));

        $paginator->getCollection()->transform(function (Lexeme $lexeme): Lexeme {
            $lexeme->setAttribute(
                'coverage_state',
                GrammarCoverageStateResolver::forLexeme(
                    (int) $lexeme->examples_count,
                    (int) $lexeme->rules_count,
                    (int) $lexeme->content_links_count
                )
            );

            return $lexeme;
        });

        return $paginator;
    }

    public function createLexeme(array $data): Lexeme
    {
        /** @var Lexeme $lexeme */
        $lexeme = DB::transaction(function () use ($data): Lexeme {
            $lexeme = Lexeme::query()->create([
                'slug' => $this->resolveUniqueSlug(Lexeme::class, $data['slug'] ?? null, $data['lemma']),
                'language' => $data['language'] ?? 'en',
                'lemma' => $data['lemma'],
                'normalized_lemma' => Str::lower($data['normalized_lemma'] ?? $data['lemma']),
                'part_of_speech' => $data['part_of_speech'] ?? null,
                'status' => $data['status'] ?? Lexeme::STATUS_DRAFT,
                'level' => $data['level'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLexemeExamples($lexeme, $data['examples'] ?? []);
            $this->syncLexemeRules($lexeme, $data['rule_ids'] ?? []);
            $this->syncLexemeAssociations($lexeme, $data['associations'] ?? []);

            return $lexeme;
        });

        return $this->getLexeme($lexeme);
    }

    public function getLexeme(Lexeme $lexeme): Lexeme
    {
        $lexeme->load([
            'rules:id,topic_id,slug,title,status,level',
            'examples:id,lexeme_id,language,example,translation,is_primary,sort_order',
            'associations.relatedLexeme:id,slug,lemma,normalized_lemma',
        ])->loadCount(['examples', 'rules', 'contentLinks', 'associations']);

        $lexeme->setAttribute(
            'coverage_state',
            GrammarCoverageStateResolver::forLexeme(
                (int) $lexeme->examples_count,
                (int) $lexeme->rules_count,
                (int) $lexeme->content_links_count
            )
        );

        return $lexeme;
    }

    public function updateLexeme(Lexeme $lexeme, array $data): Lexeme
    {
        DB::transaction(function () use ($lexeme, $data): void {
            $lexeme->fill([
                'slug' => array_key_exists('slug', $data)
                    ? $this->resolveUniqueSlug(Lexeme::class, $data['slug'], $data['lemma'] ?? $lexeme->lemma, (int) $lexeme->id)
                    : $lexeme->slug,
                'language' => $data['language'] ?? $lexeme->language,
                'lemma' => $data['lemma'] ?? $lexeme->lemma,
                'normalized_lemma' => array_key_exists('normalized_lemma', $data)
                    ? Str::lower((string) $data['normalized_lemma'])
                    : $lexeme->normalized_lemma,
                'part_of_speech' => array_key_exists('part_of_speech', $data) ? $data['part_of_speech'] : $lexeme->part_of_speech,
                'status' => $data['status'] ?? $lexeme->status,
                'level' => array_key_exists('level', $data) ? $data['level'] : $lexeme->level,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $lexeme->notes,
            ])->save();

            if (array_key_exists('examples', $data)) {
                $this->syncLexemeExamples($lexeme, $data['examples'] ?? []);
            }

            if (array_key_exists('rule_ids', $data)) {
                $this->syncLexemeRules($lexeme, $data['rule_ids'] ?? []);
            }

            if (array_key_exists('associations', $data)) {
                $this->syncLexemeAssociations($lexeme, $data['associations'] ?? []);
            }
        });

        return $this->getLexeme($lexeme->fresh());
    }

    public function deleteLexeme(Lexeme $lexeme): void
    {
        $lexeme->delete();
    }

    public function getCoverageSummary(array $filters = []): array
    {
        $topics = $this->paginateTopics(array_merge($filters, ['per_page' => 1000]))->getCollection();
        $rules = $this->paginateRules(array_merge($filters, ['per_page' => 1000]))->getCollection();
        $lexemes = $this->paginateLexemes(array_merge($filters, ['per_page' => 1000]))->getCollection();

        return [
            'topics' => [
                'total' => $topics->count(),
                'empty' => $topics->where('coverage_state', 'empty')->count(),
                'needs_coverage' => $topics->where('coverage_state', 'needs_coverage')->count(),
                'covered' => $topics->where('coverage_state', 'covered')->count(),
            ],
            'rules' => [
                'total' => $rules->count(),
                'needs_examples' => $rules->where('coverage_state', 'needs_examples')->count(),
                'needs_lexemes' => $rules->where('coverage_state', 'needs_lexemes')->count(),
                'needs_content' => $rules->where('coverage_state', 'needs_content')->count(),
                'covered' => $rules->where('coverage_state', 'covered')->count(),
            ],
            'lexemes' => [
                'total' => $lexemes->count(),
                'needs_examples' => $lexemes->where('coverage_state', 'needs_examples')->count(),
                'needs_rules' => $lexemes->where('coverage_state', 'needs_rules')->count(),
                'needs_content' => $lexemes->where('coverage_state', 'needs_content')->count(),
                'covered' => $lexemes->where('coverage_state', 'covered')->count(),
            ],
        ];
    }

    private function applyTopicCoverageFilter(Builder $query, string $coverageState): void
    {
        match ($coverageState) {
            'empty' => $query->doesntHave('rules'),
            'covered' => $query->has('rules')->whereDoesntHave('rules', fn (Builder $builder) => $this->applyRuleCoverageFilter($builder, 'uncovered')),
            'needs_coverage' => $query->where(function (Builder $builder): void {
                $builder->doesntHave('rules')
                    ->orWhereHas('rules', fn (Builder $ruleQuery) => $this->applyRuleCoverageFilter($ruleQuery, 'uncovered'));
            }),
            default => null,
        };
    }

    private function applyRuleCoverageFilter(Builder $query, string $coverageState): void
    {
        match ($coverageState) {
            'needs_examples' => $query->doesntHave('examples'),
            'needs_lexemes' => $query->has('examples')->doesntHave('lexemes'),
            'needs_content' => $query->has('examples')->has('lexemes')->doesntHave('contentLinks'),
            'covered' => $query->has('examples')->has('lexemes')->has('contentLinks'),
            'uncovered' => $query->where(function (Builder $builder): void {
                $builder->doesntHave('examples')
                    ->orWhere(fn (Builder $inner) => $inner->has('examples')->doesntHave('lexemes'))
                    ->orWhere(fn (Builder $inner) => $inner->has('examples')->has('lexemes')->doesntHave('contentLinks'));
            }),
            default => null,
        };
    }

    private function applyLexemeCoverageFilter(Builder $query, string $coverageState): void
    {
        match ($coverageState) {
            'needs_examples' => $query->doesntHave('examples'),
            'needs_rules' => $query->has('examples')->doesntHave('rules'),
            'needs_content' => $query->has('examples')->has('rules')->doesntHave('contentLinks'),
            'covered' => $query->has('examples')->has('rules')->has('contentLinks'),
            default => null,
        };
    }

    private function syncRuleExamples(GrammarRule $rule, array $examples): void
    {
        // The admin payload has only text fields; an unchanged sentence keeps
        // what the admin form does not carry (source content, AI marking — VIK-39).
        $kept = $rule->examples()->get()->keyBy(fn (GrammarRuleExample $example): string => $example->example)
            ->map(fn (GrammarRuleExample $example): array => $example->only(['content_id', 'origin', 'kind', 'target_spans', 'mistake', 'translation_language']));

        $rule->examples()->delete();

        foreach (array_values($examples) as $index => $example) {
            $rule->examples()->create(($kept[$example['example']] ?? []) + [
                'language' => $example['language'] ?? $rule->language,
                'example' => $example['example'],
                'translation' => $example['translation'] ?? null,
                'is_primary' => (bool) ($example['is_primary'] ?? false),
                'sort_order' => $example['sort_order'] ?? (($index + 1) * 10),
            ]);
        }
    }

    private function syncRuleLexemes(GrammarRule $rule, array $lexemeIds): void
    {
        $syncData = [];

        foreach (array_values($lexemeIds) as $index => $lexemeId) {
            $syncData[$lexemeId] = ['sort_order' => ($index + 1) * 10];
        }

        $rule->lexemes()->sync($syncData);
    }

    private function syncLexemeExamples(Lexeme $lexeme, array $examples): void
    {
        $lexeme->examples()->delete();

        foreach (array_values($examples) as $index => $example) {
            $lexeme->examples()->create([
                'language' => $example['language'] ?? $lexeme->language,
                'example' => $example['example'],
                'translation' => $example['translation'] ?? null,
                'is_primary' => (bool) ($example['is_primary'] ?? false),
                'sort_order' => $example['sort_order'] ?? (($index + 1) * 10),
            ]);
        }
    }

    private function syncLexemeRules(Lexeme $lexeme, array $ruleIds): void
    {
        $syncData = [];

        foreach (array_values($ruleIds) as $index => $ruleId) {
            $syncData[$ruleId] = ['sort_order' => ($index + 1) * 10];
        }

        $lexeme->rules()->sync($syncData);
    }

    private function syncLexemeAssociations(Lexeme $lexeme, array $associations): void
    {
        $lexeme->associations()->delete();

        foreach (array_values($associations) as $index => $association) {
            $relatedLexemeId = (int) $association['related_lexeme_id'];

            if ($relatedLexemeId === (int) $lexeme->id) {
                continue;
            }

            $lexeme->associations()->create([
                'related_lexeme_id' => $relatedLexemeId,
                'type' => $association['type'] ?? 'related',
                'note' => $association['note'] ?? null,
                'sort_order' => $association['sort_order'] ?? (($index + 1) * 10),
            ]);
        }
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function resolveUniqueSlug(string $modelClass, ?string $slug, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug !== null && $slug !== '' ? $slug : $fallback);
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 2;

        while ($this->slugExists($modelClass, $candidate, $ignoreId)) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function slugExists(string $modelClass, string $slug, ?int $ignoreId = null): bool
    {
        $query = $modelClass::query()->where('slug', $slug);

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
