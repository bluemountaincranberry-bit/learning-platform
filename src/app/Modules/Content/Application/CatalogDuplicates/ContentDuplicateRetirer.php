<?php

namespace App\Modules\Content\Application\CatalogDuplicates;

use App\Modules\Content\Domain\Models\Content;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VIK-16: takes a duplicate content (same source as a kept one) out of the
 * catalog by rejecting it with a "Duplicate of #N" note. It is never
 * deleted, and a rejected content can be sent back to pending.
 *
 * Only a duplicate nobody has learned from is retired: its learner
 * footprint is found by walking the database's foreign keys from the
 * content through data the pipeline derived from it (transcript, words,
 * analysis runs). Any other row on that walk (progress, SRS, attempts,
 * sessions, or a table added later) means a learner used it — such a pair
 * is refused and reported for a manual merge.
 */
final class ContentDuplicateRetirer
{
    /** Pipeline-derived tables: walked through, not learner data. */
    private const DERIVED = [
        'transcript_segments', 'transcript_segment_translations', 'transcript_segment_lexemes',
        'content_lexemes', 'cloze_examples', 'lexeme_embeddings',
        'ai_analysis_runs', 'content_lexeme_candidates', 'content_grammar_candidates',
        'content_rule_links',
    ];

    /**
     * Catalog rows that only cite the content as their source and outlive it
     * (their foreign key is "set null"); retiring the content changes nothing
     * for them or for anything hanging off them.
     */
    private const CITING = ['lexeme_examples', 'lexeme_translations', 'grammar_rule_examples'];

    public function __construct(private readonly ForeignKeyGraph $foreignKeys) {}

    /**
     * Tables holding learner data for this content; empty = safe to retire.
     *
     * @return list<string>
     */
    public function learnerFootprint(int $contentId): array
    {
        $found = [];
        $queue = [['contents', [$contentId]]];
        $seen = [];

        while ($queue !== []) {
            [$table, $ids] = array_shift($queue);

            foreach ($this->foreignKeys->referencesTo($table) as $reference) {
                $key = $reference['table'].'.'.$reference['column'];
                if (isset($seen[$key]) || in_array($reference['table'], self::CITING, true)) {
                    continue;
                }
                $seen[$key] = true;

                $isDerived = in_array($reference['table'], self::DERIVED, true);
                $walkChildren = $isDerived && Schema::hasColumn($reference['table'], 'id');

                // Chunked: a long video has thousands of segments, beyond the
                // bind-parameter limit of one IN (...) list.
                foreach (array_chunk($ids, 500) as $chunk) {
                    $rows = DB::table($reference['table'])->whereIn($reference['column'], $chunk);

                    if (! $isDerived) {
                        if ($rows->exists()) {
                            $found[] = $reference['table'];

                            break;
                        }

                        continue;
                    }

                    if ($walkChildren) {
                        $childIds = $rows->pluck('id')->all();
                        if ($childIds !== []) {
                            $queue[] = [$reference['table'], $childIds];
                        }
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function retire(int $keepId, int $duplicateId): void
    {
        DB::transaction(function () use ($keepId, $duplicateId): void {
            $keep = Content::query()->lockForUpdate()->findOrFail($keepId);
            $duplicate = Content::query()->lockForUpdate()->findOrFail($duplicateId);

            if ($keep->id === $duplicate->id || $keep->source_key === null || $keep->source_key !== $duplicate->source_key) {
                throw new DuplicateMergeRefused(sprintf('Contents #%d and #%d are not the same source.', $keep->id, $duplicate->id));
            }
            if ($duplicate->status === 'rejected') {
                throw new DuplicateMergeRefused(sprintf('Content #%d is already rejected.', $duplicate->id));
            }
            if (! $duplicate->canTransitionTo('rejected')) {
                throw new DuplicateMergeRefused(sprintf('Content #%d is %s and cannot be rejected now; retry later.', $duplicate->id, $duplicate->status));
            }

            $footprint = $this->learnerFootprint($duplicate->id);
            if ($footprint !== []) {
                throw new DuplicateMergeRefused(sprintf(
                    'Content #%d has learner data in %s; merge it by hand.',
                    $duplicate->id,
                    implode(', ', $footprint),
                ));
            }

            $duplicate->forceFill([
                'status' => 'rejected',
                'moderation_comment' => sprintf('Duplicate of #%d (same video). Retired by catalog:merge-duplicates.', $keep->id),
                'moderated_at' => now(),
            ])->save();
        });
    }
}
