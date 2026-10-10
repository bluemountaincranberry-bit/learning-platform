<?php

namespace App\Modules\Content\Application;

use App\Modules\Content\Application\Support\UniqueSlugResolver;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApplyGrammarRuleEditorDraft
{
    public function __construct(
        private readonly UniqueSlugResolver $slugResolver,
    ) {}

    public function execute(GrammarRule $rule, Model $actor, int $expectedVersion, array $draft): GrammarRule
    {
        return DB::transaction(function () use ($rule, $actor, $expectedVersion, $draft): GrammarRule {
            $lockedRule = GrammarRule::query()->lockForUpdate()->findOrFail($rule->id);
            if ((int) $lockedRule->editor_version !== $expectedVersion) {
                throw new ConflictHttpException('This grammar rule changed while you were editing. Reload it before applying your draft.');
            }

            $before = $this->snapshot($lockedRule);
            $slug = $this->slugResolver->resolve(GrammarRule::class, null, $draft['title'], (int) $lockedRule->id);
            $lockedRule->fill([
                'title' => $draft['title'], 'slug' => $slug,
                'summary' => $draft['summary'] ?? null, 'body' => $draft['body'] ?? null,
            ])->save();

            $retainedExampleIds = [];
            foreach (array_values($draft['examples']) as $index => $data) {
                $attributes = [
                    'language' => $data['language'] ?? $lockedRule->language,
                    'example' => $data['example'],
                    'translation' => $data['translation'] ?? null,
                    'is_primary' => (bool) ($data['is_primary'] ?? false),
                    'sort_order' => $data['sort_order'] ?? (($index + 1) * 10),
                    'archived_at' => null,
                ];
                if (array_key_exists('target_spans', $data)) {
                    $attributes['target_spans'] = $data['target_spans'];
                }

                if (isset($data['id'])) {
                    $example = $lockedRule->allExamples()->whereKey($data['id'])->firstOrFail();
                    $example->fill($attributes)->save();
                    $retainedExampleIds[] = $example->id;
                } else {
                    $example = $lockedRule->allExamples()->create($attributes + ['origin' => GrammarRuleExample::ORIGIN_AI]);
                    $retainedExampleIds[] = $example->id;
                }
            }

            $lockedRule->examples()->whereNotIn('id', $retainedExampleIds)->update(['archived_at' => now()]);
            $updated = $lockedRule->fresh();

            $after = $this->snapshot($updated);
            if ($before !== $after) {
                $updated->revisions()->create([
                    'causer_type' => $actor::class,
                    'causer_id' => $actor->getKey(),
                    'source' => 'admin',
                    'changes' => ['editor_snapshot' => ['old' => $before, 'new' => $after]],
                ]);
            }

            return $updated;
        });
    }

    /** @return array{title: string, summary: ?string, body: ?string, examples: list<array<string, mixed>>} */
    private function snapshot(GrammarRule $rule): array
    {
        return [
            'title' => $rule->title,
            'summary' => $rule->summary,
            'body' => $rule->body,
            'examples' => $rule->examples()->orderBy('sort_order')->get()->map(fn ($example): array => [
                'id' => (int) $example->id,
                'language' => $example->language,
                'example' => $example->example,
                'translation' => $example->translation,
                'is_primary' => (bool) $example->is_primary,
                'sort_order' => (int) $example->sort_order,
                'target_spans' => $example->target_spans,
            ])->all(),
        ];
    }
}
