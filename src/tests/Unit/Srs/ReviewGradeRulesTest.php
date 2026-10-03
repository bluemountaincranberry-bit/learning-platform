<?php

use App\Modules\Srs\Domain\ReviewGradeRules;

test('review grades accept the public 0 to 5 range', function () {
    expect(ReviewGradeRules::isValid(0))->toBeTrue()
        ->and(ReviewGradeRules::isValid(5))->toBeTrue()
        ->and(ReviewGradeRules::isValid(-1))->toBeFalse()
        ->and(ReviewGradeRules::isValid(6))->toBeFalse();
});

test('grades at or below two are failing', function () {
    expect(ReviewGradeRules::isFailing(2))->toBeTrue()
        ->and(ReviewGradeRules::isFailing(3))->toBeFalse();
});
