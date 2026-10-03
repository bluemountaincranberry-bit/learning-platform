<?php

namespace App\Modules\Content\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentProcessingRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $contentId
    ) {}
}
