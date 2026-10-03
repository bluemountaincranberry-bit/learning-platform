<?php

namespace App\Contracts\Ai;

use App\Modules\Ai\Application\Data\SentenceAnswerGradingInput;
use App\Modules\Ai\Application\Data\SentenceAnswerGradingResult;

interface SentenceAnswerGradingCapability
{
    public function grade(SentenceAnswerGradingInput $input): SentenceAnswerGradingResult;
}
