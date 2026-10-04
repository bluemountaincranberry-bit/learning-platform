<?php

namespace App\Console\Commands;

use App\Modules\Content\Application\Contracts\GrammarRuleExampleGenerationsInterface;
use App\Modules\Content\Domain\Models\GrammarRuleExampleGeneration;
use Illuminate\Console\Command;

/**
 * VIK-39: tops up every grammar rule (not archived) that has fewer than
 * `--min` examples with AI examples, up to `--target`. Goes through the
 * same queued batches as "More examples" on the rule page (one active batch
 * per rule, status in grammar_rule_example_generations), as system batches
 * without a daily limit. `--sync` runs the batches in this process instead
 * of the queue and prints the result per rule.
 */
class BackfillGrammarRuleExamplesCommand extends Command
{
    protected $signature = 'grammar:backfill-examples
        {--min= : rules with fewer examples than this get more (default ai.examples.min_per_rule)}
        {--target= : how many examples a topped-up rule should have (default ai.examples.backfill_target)}
        {--translation= : translation language (default ai.examples.translation_language)}
        {--rule=* : only these rule ids}
        {--sync : run the AI batches now instead of queueing them}
        {--dry-run : only list the rules that would get examples}';

    protected $description = 'Generate AI examples for grammar rules that have too few.';

    private const MAX_BATCH = 10;

    public function handle(GrammarRuleExampleGenerationsInterface $generations): int
    {
        $min = (int) ($this->option('min') ?? config('ai.examples.min_per_rule', 6));
        $target = max($min, (int) ($this->option('target') ?? config('ai.examples.backfill_target', 8)));
        $translation = (string) ($this->option('translation') ?? config('ai.examples.translation_language', 'ru'));
        $onlyRuleIds = array_map('intval', (array) $this->option('rule'));

        $rules = $generations->rulesBelow($min, $onlyRuleIds);

        if ($rules === []) {
            $this->info("Every rule has at least {$min} examples.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(['rule', 'examples', 'would add'], collect($rules)
                ->map(fn (int $count, int $ruleId): array => [$ruleId, $count, min(self::MAX_BATCH, $target - $count)])
                ->values()->all());

            return self::SUCCESS;
        }

        if ($this->option('sync')) {
            config(['queue.default' => 'sync']);
        }

        $rows = [];
        foreach ($rules as $ruleId => $count) {
            $size = min(self::MAX_BATCH, $target - $count);
            $result = $generations->request($ruleId, null, $size, $translation !== '' ? $translation : null);
            $rows[] = [$ruleId, $count, $size, $result->status];
        }

        if ($this->option('sync')) {
            // One AI answer can come back short (dropped duplicates, unmarked
            // sentences); give those rules one more batch.
            foreach ($generations->rulesBelow($min, array_keys($rules)) as $ruleId => $count) {
                $generations->request($ruleId, null, min(self::MAX_BATCH, $target - $count), $translation !== '' ? $translation : null);
            }

            $after = $generations->rulesBelow(PHP_INT_MAX, array_keys($rules));
            $rows = array_map(function (array $row) use ($after): array {
                $latest = GrammarRuleExampleGeneration::query()->where('grammar_rule_id', $row[0])->latest('id')->first();

                return [$row[0], $row[1], $after[$row[0]] ?? $row[1], $latest?->status ?? $row[3], $latest?->error ?? ''];
            }, $rows);
            $this->table(['rule', 'before', 'after', 'status', 'error'], $rows);

            $stillBelow = $generations->rulesBelow($min, array_keys($rules));
            if ($stillBelow !== []) {
                $this->warn(count($stillBelow).' rule(s) still have fewer than '.$min.' examples: '.implode(', ', array_keys($stillBelow)));

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->table(['rule', 'examples', 'requested', 'status'], $rows);

        return self::SUCCESS;
    }
}
