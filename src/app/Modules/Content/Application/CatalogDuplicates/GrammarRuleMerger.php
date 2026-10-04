<?php

namespace App\Modules\Content\Application\CatalogDuplicates;

use App\Modules\Content\Application\Contracts\GrammarRuleMergeParticipant;
use App\Modules\Content\Application\GrammarRuleExampleMarkup;
use App\Modules\Content\Domain\Models\ContentGrammarCandidate;
use App\Modules\Content\Domain\Models\ContentRuleLink;
use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarRuleExampleGeneration;
use App\Modules\Content\Domain\Models\GrammarRuleExampleHide;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use Illuminate\Support\Facades\DB;

/**
 * VIK-16: folds a duplicate grammar rule into the rule that is kept.
 *
 * Everything that pointed at the duplicate (content links, examples and
 * learners' hides, exercises, attempts, candidates, and — through
 * GrammarRuleMergeParticipant — learners' progress and embeddings) is moved
 * to the kept rule. The duplicate itself is archived, never deleted, so its
 * row and revision history stay inspectable.
 *
 * Atomic: one transaction. Before archiving, the database's own foreign
 * keys are checked; if any row still points at the duplicate (a table this
 * code does not know about yet) the whole merge rolls back.
 */
final class GrammarRuleMerger
{
    /** @param iterable<GrammarRuleMergeParticipant> $participants */
    public function __construct(
        private readonly ForeignKeyGraph $foreignKeys,
        private readonly iterable $participants,
    ) {}

    public function merge(int $keepId, int $duplicateId): void
    {
        DB::transaction(function () use ($keepId, $duplicateId): void {
            $keep = GrammarRule::query()->lockForUpdate()->findOrFail($keepId);
            $duplicate = GrammarRule::query()->lockForUpdate()->findOrFail($duplicateId);
            $this->assertMergeable($keep, $duplicate);

            $this->fillGaps($keep, $duplicate);
            $this->moveContentLinks($keep->id, $duplicate->id);
            $this->moveLexemes($keep->id, $duplicate->id);
            $this->moveExamples($keep->id, $duplicate->id);

            foreach ([GrammarRuleExercise::class, GrammarExamAttempt::class, GrammarRuleExampleGeneration::class] as $model) {
                $model::query()->where('grammar_rule_id', $duplicate->id)->update(['grammar_rule_id' => $keep->id]);
            }
            ContentGrammarCandidate::query()->where('matched_grammar_rule_id', $duplicate->id)
                ->update(['matched_grammar_rule_id' => $keep->id]);

            foreach ($this->participants as $participant) {
                $participant->reassignGrammarRule($duplicate->id, $keep->id);
            }

            $remaining = $this->foreignKeys->remainingReferences('grammar_rules', $duplicate->id);
            if ($remaining !== []) {
                throw new DuplicateMergeRefused(sprintf(
                    'Rule #%d is still referenced by %s; add it to the merge before retrying.',
                    $duplicate->id,
                    implode(', ', array_keys($remaining)),
                ));
            }

            $duplicate->status = GrammarRule::STATUS_ARCHIVED;
            $duplicate->save();
        });
    }

    private function assertMergeable(GrammarRule $keep, GrammarRule $duplicate): void
    {
        if ($keep->id === $duplicate->id) {
            throw new DuplicateMergeRefused('A rule cannot be merged into itself.');
        }
        if ($keep->language !== $duplicate->language) {
            throw new DuplicateMergeRefused(sprintf('Rules #%d and #%d are in different languages.', $keep->id, $duplicate->id));
        }
        if ($keep->status === GrammarRule::STATUS_ARCHIVED) {
            throw new DuplicateMergeRefused(sprintf('Rule #%d is archived and cannot be kept.', $keep->id));
        }
        if ($duplicate->status === GrammarRule::STATUS_ARCHIVED) {
            throw new DuplicateMergeRefused(sprintf('Rule #%d is already archived (merged).', $duplicate->id));
        }
    }

    /** The kept rule keeps its own text; only empty fields are taken from the duplicate. */
    private function fillGaps(GrammarRule $keep, GrammarRule $duplicate): void
    {
        foreach (['summary', 'body', 'level'] as $field) {
            if (blank($keep->{$field}) && filled($duplicate->{$field})) {
                $keep->{$field} = $duplicate->{$field};
            }
        }
        if ($keep->isDirty()) {
            $keep->save();
        }
    }

    private function moveContentLinks(int $keepId, int $duplicateId): void
    {
        $linked = ContentRuleLink::query()->where('grammar_rule_id', $keepId)->pluck('content_id')->all();

        ContentRuleLink::query()->where('grammar_rule_id', $duplicateId)->whereIn('content_id', $linked)->delete();
        ContentRuleLink::query()->where('grammar_rule_id', $duplicateId)->update(['grammar_rule_id' => $keepId]);
    }

    private function moveLexemes(int $keepId, int $duplicateId): void
    {
        $pivot = DB::table('grammar_rule_lexeme');
        $linked = (clone $pivot)->where('grammar_rule_id', $keepId)->pluck('lexeme_id')->all();

        (clone $pivot)->where('grammar_rule_id', $duplicateId)->whereIn('lexeme_id', $linked)->delete();
        (clone $pivot)->where('grammar_rule_id', $duplicateId)->update(['grammar_rule_id' => $keepId]);
    }

    /**
     * The same sentence on both rules is kept once (the kept rule's row);
     * learners' hides of the dropped twin move to the kept row, so a hidden
     * example stays hidden.
     */
    private function moveExamples(int $keepId, int $duplicateId): void
    {
        $kept = GrammarRuleExample::query()->where('grammar_rule_id', $keepId)->get()
            ->keyBy(fn (GrammarRuleExample $example): string => GrammarRuleExampleMarkup::dedupKey((string) $example->example));
        $keepHasPrimary = $kept->contains(fn (GrammarRuleExample $example): bool => (bool) $example->is_primary);

        foreach (GrammarRuleExample::query()->where('grammar_rule_id', $duplicateId)->get() as $example) {
            $twin = $kept->get(GrammarRuleExampleMarkup::dedupKey((string) $example->example));

            if ($twin === null) {
                $example->grammar_rule_id = $keepId;
                if ($keepHasPrimary) {
                    $example->is_primary = false;
                }
                $example->save();
                $keepHasPrimary = $keepHasPrimary || (bool) $example->is_primary;

                continue;
            }

            foreach (GrammarRuleExampleHide::query()->where('grammar_rule_example_id', $example->id)->get() as $hide) {
                $alreadyHidden = GrammarRuleExampleHide::query()
                    ->where('user_id', $hide->user_id)->where('grammar_rule_example_id', $twin->id)->exists();
                $alreadyHidden ? $hide->delete() : $hide->update(['grammar_rule_example_id' => $twin->id]);
            }

            $remaining = $this->foreignKeys->remainingReferences('grammar_rule_examples', $example->id);
            if ($remaining !== []) {
                throw new DuplicateMergeRefused(sprintf(
                    'Example #%d is still referenced by %s; add it to the merge before retrying.',
                    $example->id,
                    implode(', ', array_keys($remaining)),
                ));
            }

            $example->delete();
        }
    }
}
