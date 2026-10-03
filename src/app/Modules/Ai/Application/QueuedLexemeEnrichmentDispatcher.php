<?php

namespace App\Modules\Ai\Application;

use App\Contracts\Ai\LexemeEnrichmentDispatcher;
use App\Modules\Ai\Interfaces\Jobs\EnrichLexemeAssociationsJob;

final class QueuedLexemeEnrichmentDispatcher implements LexemeEnrichmentDispatcher
{
    public function dispatchFor(int $lexemeId): void
    {
        EnrichLexemeAssociationsJob::dispatch($lexemeId);
    }
}
