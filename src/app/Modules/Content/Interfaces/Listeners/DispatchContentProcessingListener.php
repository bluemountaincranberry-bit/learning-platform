<?php

namespace App\Modules\Content\Interfaces\Listeners;

use App\Modules\Content\Application\Contracts\ContentProcessingOrchestratorInterface;
use App\Modules\Content\Domain\Events\ContentProcessingRequested;
use App\Modules\Content\Domain\Models\Content;

class DispatchContentProcessingListener
{
    public function __construct(
        private ContentProcessingOrchestratorInterface $contentProcessingOrchestrator
    ) {}

    public function handle(ContentProcessingRequested $event): void
    {
        $content = Content::query()->find($event->contentId);

        if (! $content) {
            return;
        }

        $this->contentProcessingOrchestrator->request($content);
    }
}
