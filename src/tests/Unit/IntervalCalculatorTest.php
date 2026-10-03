<?php

use App\Modules\Srs\Domain\IntervalCalculator;

test('interval calculator decreases to one for low grade', function () {
    $calculator = new IntervalCalculator();

    expect($calculator->nextInterval(5, 2.5, 2))->toBe(1);
});

test('interval calculator increases for high grade', function () {
    $calculator = new IntervalCalculator();

    expect($calculator->nextInterval(3, 2.6, 5))->toBeGreaterThan(3);
});

test('ease factor is bounded', function () {
    $calculator = new IntervalCalculator();

    expect($calculator->nextEase(2.8, 5))->toBe(2.8);
    expect($calculator->nextEase(1.3, 1))->toBe(1.3);
});

test('calculate returns a typed decision and rejects invalid grades', function () {
    $calculator = new IntervalCalculator();

    $decision = $calculator->calculate(4, 2.5, 4);

    expect($decision->intervalDays)->toBe(10)
        ->and($decision->easeFactor)->toBe(2.55)
        ->and(fn () => $calculator->calculate(4, 2.5, 6))
        ->toThrow(InvalidArgumentException::class);
});
