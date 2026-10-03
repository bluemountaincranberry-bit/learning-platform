<?php

namespace App\Modules\Content\Application\Contracts;

interface CandidateApplicationInterface
{
    /** @return array{lexemes:int, grammar:int} */
    public function apply(object $run): array;
}
