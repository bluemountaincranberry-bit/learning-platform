<?php

namespace App\Modules\Srs\Application\Contracts;

use App\Modules\Srs\Application\Data\ReviewOutcome;

interface ReviewOutcomeHandlerInterface
{
    public function handle(ReviewOutcome $outcome): void;
}
