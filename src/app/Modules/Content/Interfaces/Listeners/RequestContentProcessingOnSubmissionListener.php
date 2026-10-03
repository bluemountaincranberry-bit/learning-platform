<?php

namespace App\Modules\Content\Interfaces\Listeners;

use App\Modules\Content\Domain\Events\ContentProcessingRequested;
use App\Modules\Content\Domain\Events\ContentSubmitted;

class RequestContentProcessingOnSubmissionListener
{
    public function handle(ContentSubmitted $event): void
    {
        ContentProcessingRequested::dispatch($event->contentId);
    }
}
