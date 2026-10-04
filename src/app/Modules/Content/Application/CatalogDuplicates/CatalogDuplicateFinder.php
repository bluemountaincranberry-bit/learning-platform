<?php

namespace App\Modules\Content\Application\CatalogDuplicates;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;

/**
 * VIK-16: finds exact duplicates already in the catalog and decides which
 * row is kept.
 *
 * - Grammar rules: same language + same title identity, not archived.
 *   Kept: a published rule before a draft, then the oldest.
 * - Contents: same source key (one YouTube video), not rejected. Kept: the
 *   one learners used; if several were used, the pair is left for a manual
 *   merge; if none, the oldest.
 */
final class CatalogDuplicateFinder
{
    public function __construct(private readonly ContentDuplicateRetirer $contents) {}

    /** @return list<array{keep: GrammarRule, duplicates: list<GrammarRule>}> */
    public function grammarRuleGroups(): array
    {
        return GrammarRule::query()
            ->where('status', '!=', GrammarRule::STATUS_ARCHIVED)
            ->whereNotNull('normalized_title')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (GrammarRule $rule): string => $rule->language."\0".$rule->normalized_title)
            ->filter(fn ($group): bool => $group->count() > 1)
            ->map(function ($group): array {
                $ordered = $group->sortBy([
                    fn (GrammarRule $a, GrammarRule $b): int => ($b->status === GrammarRule::STATUS_PUBLISHED) <=> ($a->status === GrammarRule::STATUS_PUBLISHED),
                    fn (GrammarRule $a, GrammarRule $b): int => $a->id <=> $b->id,
                ])->values();

                return ['keep' => $ordered->first(), 'duplicates' => $ordered->slice(1)->values()->all()];
            })
            ->values()
            ->all();
    }

    /**
     * `keep` is null when more than one copy carries learner data.
     *
     * @return list<array{keep: ?Content, duplicates: list<Content>, footprints: array<int, list<string>>}>
     */
    public function contentGroups(): array
    {
        return Content::query()
            ->where('status', '!=', 'rejected')
            ->whereNotNull('source_key')
            ->orderBy('id')
            ->get()
            ->groupBy('source_key')
            ->filter(fn ($group): bool => $group->count() > 1)
            ->map(function ($group): array {
                $footprints = $group->mapWithKeys(fn (Content $content): array => [$content->id => $this->contents->learnerFootprint($content->id)])->all();
                $used = $group->filter(fn (Content $content): bool => $footprints[$content->id] !== [])->values();

                $keep = match (true) {
                    $used->count() > 1 => null,
                    $used->count() === 1 => $used->first(),
                    default => $group->first(),
                };

                return [
                    'keep' => $keep,
                    'duplicates' => $group->reject(fn (Content $content): bool => $keep !== null && $content->id === $keep->id)->values()->all(),
                    'footprints' => $footprints,
                ];
            })
            ->values()
            ->all();
    }
}
