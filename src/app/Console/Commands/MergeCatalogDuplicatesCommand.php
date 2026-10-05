<?php

namespace App\Console\Commands;

use App\Modules\Content\Application\CatalogDuplicates\CatalogDuplicateFinder;
use App\Modules\Content\Application\CatalogDuplicates\ContentDuplicateRetirer;
use App\Modules\Content\Application\CatalogDuplicates\DuplicateMergeRefused;
use App\Modules\Content\Application\CatalogDuplicates\GrammarRuleMerger;
use App\Modules\Content\Domain\Models\GrammarRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VIK-16: merges duplicate grammar rules and retires duplicate contents.
 *
 * Dry run by default: every step really runs inside one transaction that
 * is always rolled back, so the plan also shows what would be refused.
 * `--apply` performs it, one transaction per pair. Idempotent: merged rules are
 * archived and retired contents rejected, so a second run finds nothing.
 * Nothing is deleted except a duplicate rule's identical example sentences
 * (their learners' hides move to the kept sentence) and its embedding.
 *
 * Near-duplicates with different titles are never guessed; pass them
 * explicitly: `--rule=14:26` merges rule #26 into rule #14.
 */
class MergeCatalogDuplicatesCommand extends Command
{
    protected $signature = 'catalog:merge-duplicates
        {--apply : Perform the merge (default is a dry run)}
        {--rule=* : Explicit KEEP:DUPLICATE grammar rule ids to merge, e.g. 14:26}';

    protected $description = 'Merge duplicate grammar rules and retire duplicate contents (dry run unless --apply)';

    public function handle(CatalogDuplicateFinder $finder, GrammarRuleMerger $ruleMerger, ContentDuplicateRetirer $contentRetirer): int
    {
        $pairs = $this->explicitRulePairs();
        if ($pairs === null) {
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $lock = Cache::lock('catalog:merge-duplicates', 3600);
        if ($apply && ! $lock->get()) {
            $this->error('Another merge is running. Try again after it finishes.');

            return self::FAILURE;
        }

        if (! $apply) {
            DB::beginTransaction();
        }

        try {
            $this->line($apply ? 'Applying the merge.' : 'Dry run: nothing is written. Re-run with --apply to merge.');

            $ruleGroups = $finder->grammarRuleGroups();
            $contentGroups = $finder->contentGroups();
            foreach ($ruleGroups as $group) {
                foreach ($group['duplicates'] as $duplicate) {
                    $pairs[] = [$group['keep']->id, $duplicate->id];
                }
            }
            // A pair named with --rule may also be auto-detected.
            $pairs = array_values(array_unique($pairs, SORT_REGULAR));

            $failed = false;
            foreach ($pairs as [$keepId, $duplicateId]) {
                $failed = ! $this->step(
                    $apply,
                    sprintf('Rule #%d "%s" -> merge into #%d "%s"', $duplicateId, $this->ruleTitle($duplicateId), $keepId, $this->ruleTitle($keepId)),
                    fn () => $ruleMerger->merge($keepId, $duplicateId),
                ) || $failed;
            }

            foreach ($contentGroups as $group) {
                if ($group['keep'] === null) {
                    $this->warn(sprintf(
                        'Contents %s are the same video and more than one has learner data; merge them by hand.',
                        implode(', ', array_map(fn ($content) => '#'.$content->id, $group['duplicates'])),
                    ));
                    $failed = true;

                    continue;
                }

                foreach ($group['duplicates'] as $duplicate) {
                    $failed = ! $this->step(
                        $apply,
                        sprintf('Content #%d "%s" -> reject as duplicate of #%d', $duplicate->id, $duplicate->title, $group['keep']->id),
                        fn () => $contentRetirer->retire($group['keep']->id, $duplicate->id),
                    ) || $failed;
                }
            }

            if ($pairs === [] && $contentGroups === []) {
                $this->info('No duplicates found.');
            }

            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            $apply ? $lock->release() : DB::rollBack();
        }
    }

    private function step(bool $apply, string $description, \Closure $action): bool
    {
        try {
            $action();

            if (! $apply) {
                $this->line('Would merge: '.$description);

                return true;
            }

            $this->info('Merged: '.$description);
            Log::info('catalog:merge-duplicates merged', ['step' => $description]);

            return true;
        } catch (DuplicateMergeRefused $e) {
            $this->error('Skipped: '.$description.' — '.$e->getMessage());

            return false;
        }
    }

    /** @return list<array{0: int, 1: int}>|null */
    private function explicitRulePairs(): ?array
    {
        $pairs = [];

        foreach ((array) $this->option('rule') as $option) {
            if (! preg_match('/^(\d+):(\d+)$/', (string) $option, $matches)) {
                $this->error(sprintf('Invalid --rule "%s"; use KEEP:DUPLICATE, e.g. 14:26.', $option));

                return null;
            }
            $pairs[] = [(int) $matches[1], (int) $matches[2]];
        }

        return $pairs;
    }

    private function ruleTitle(int $id): string
    {
        return (string) (GrammarRule::query()->whereKey($id)->value('title') ?? '(missing)');
    }
}
