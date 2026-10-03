<?php

namespace App\Modules\Srs\Application;

use App\Modules\Srs\Application\Contracts\ReviewGradePolicyInterface;
use App\Modules\Srs\Domain\ReviewGradeRules;

final class ReviewGradePolicy implements ReviewGradePolicyInterface
{
    public function failingThreshold(): int
    {
        return ReviewGradeRules::FAILING_THRESHOLD;
    }
}
