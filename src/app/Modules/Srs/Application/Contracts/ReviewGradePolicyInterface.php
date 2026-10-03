<?php

namespace App\Modules\Srs\Application\Contracts;

interface ReviewGradePolicyInterface
{
    public function failingThreshold(): int;
}
