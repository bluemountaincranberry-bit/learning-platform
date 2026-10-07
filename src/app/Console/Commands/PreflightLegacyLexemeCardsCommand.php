<?php

namespace App\Console\Commands;

use App\Modules\Srs\Application\LegacyLexemeCardPreflight;
use Illuminate\Console\Command;

final class PreflightLegacyLexemeCardsCommand extends Command
{
    protected $signature = 'srs:preflight-lexeme-cards {--json : Print the complete report as JSON}';

    protected $description = 'Audit legacy SRS card identities without changing learner data';

    public function handle(LegacyLexemeCardPreflight $preflight): int
    {
        $report = $preflight->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Metric', 'Count'], [
                ['Legacy cards', $report['cards']],
                ['Uniquely resolvable', $report['resolved']],
                ['Unresolved', count($report['unresolved'])],
            ]);

            foreach ($report['unresolved'] as $row) {
                $this->warn(sprintf(
                    'Card %d (%s, content %d): %s; candidates: %s',
                    $row['card_id'],
                    $row['item_key'],
                    $row['content_id'],
                    $row['reason'],
                    $row['candidate_lexeme_ids'] === [] ? 'none' : implode(', ', $row['candidate_lexeme_ids']),
                ));
            }
        }

        if ($report['unresolved'] !== []) {
            $this->error('No data was changed. Resolve every listed legacy key before cutover.');

            return self::FAILURE;
        }

        $this->info('All legacy card identities resolve uniquely; this command made no changes.');

        return self::SUCCESS;
    }
}
