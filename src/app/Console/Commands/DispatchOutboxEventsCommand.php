<?php

namespace App\Console\Commands;

use App\Modules\Infrastructure\Application\Outbox\OutboxDispatcher;
use Illuminate\Console\Command;

class DispatchOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:dispatch {--limit=100}';
    protected $description = 'Publish pending transactional outbox events';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $count = $dispatcher->dispatch((int) $this->option('limit'));
        $this->info("Published {$count} outbox event(s).");
        return self::SUCCESS;
    }
}
