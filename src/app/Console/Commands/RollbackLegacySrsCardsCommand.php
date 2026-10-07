<?php

namespace App\Console\Commands;

use App\Modules\Srs\Application\LegacySrsCardMigration;
use Illuminate\Console\Command;
use RuntimeException;

final class RollbackLegacySrsCardsCommand extends Command
{
    protected $signature = 'srs:rollback-legacy-cards {auditId : Applied migration audit ID}';

    protected $description = 'Restore an unapplied-write legacy SRS cutover from its transactional audit snapshot';

    public function handle(LegacySrsCardMigration $migration): int
    {
        try {
            $migration->rollback((int) $this->argument('auditId'));
            $this->info('Legacy SRS rows and review history restored from the audit snapshot.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
