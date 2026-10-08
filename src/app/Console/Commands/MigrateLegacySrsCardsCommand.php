<?php

namespace App\Console\Commands;

use App\Modules\Srs\Application\LegacySrsCardMigration;
use Illuminate\Console\Command;
use RuntimeException;

final class MigrateLegacySrsCardsCommand extends Command
{
    protected $signature = 'srs:migrate-legacy-cards
        {--apply : Apply the migration; otherwise show a read-only plan}
        {--json : Print the full read-only plan, including identity and collision details}
        {--verified-backup= : Reference for an isolated backup that has been restored and reconciled}
        {--writes-paused : Confirm learner writes and SRS workers are paused}
        {--allow-local-active : Explicitly allow cutover only when APP_ENV=local}';

    protected $description = 'Preview or transactionally migrate legacy SRS cards to canonical lexeme identities';

    public function handle(LegacySrsCardMigration $migration): int
    {
        try {
            $plan = $migration->preview();
            $report = $plan['report'];

            if (! $this->option('apply')) {
                if ($this->option('json')) {
                    $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                    return $report['unresolved'] === [] ? self::SUCCESS : self::FAILURE;
                }

                $this->table(['Metric', 'Count'], [
                    ['Legacy cards', $report['cards']],
                    ['Cards resolved', $report['resolved']],
                    ['Confidence rows', $report['confidence_rows']],
                    ['Unresolved rows', count($report['unresolved'])],
                    ['Canonical card groups', count($plan['card_groups'])],
                    ['Canonical confidence groups', count($plan['confidence_groups'])],
                ]);
                $this->line(json_encode($report['unresolved'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                $this->info('Read-only preview only. No learner data was changed.');

                return $report['unresolved'] === [] ? self::SUCCESS : self::FAILURE;
            }

            if (! $this->option('writes-paused')) {
                throw new RuntimeException('Apply requires --writes-paused after learning writes and SRS workers have been paused.');
            }

            $auditId = $migration->apply(
                (string) $this->option('verified-backup'),
                (bool) $this->option('allow-local-active'),
            );
            $this->info("Legacy SRS migration applied transactionally. Audit ID: {$auditId}");

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
