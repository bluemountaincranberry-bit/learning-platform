<?php

namespace App\Modules\Srs\Domain;

final class ReviewGradeRules
{
    public const MIN_GRADE = 0;
    public const MAX_GRADE = 5;
    public const FAILING_THRESHOLD = 2;

    public static function isValid(int $grade): bool
    {
        return $grade >= self::MIN_GRADE && $grade <= self::MAX_GRADE;
    }

    public static function isFailing(int $grade): bool
    {
        return $grade <= self::FAILING_THRESHOLD;
    }
}
