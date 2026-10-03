<?php

namespace App\Modules\Content\Application\Contracts;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Application\Data\ContentProcessingRequestResult;

interface ContentProcessingOrchestratorInterface
{
    public function request(Content $content): ContentProcessingRequestResult;
}
