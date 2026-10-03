<?php

use App\Modules\Ai\Application\VectorMath;

uses(Tests\TestCase::class);

test('cosineSimilarity is 1.0 for identical vectors', function () {
    expect(VectorMath::cosineSimilarity([1.0, 0.0], [1.0, 0.0]))->toBe(1.0);
});

test('cosineSimilarity is 0.0 for orthogonal vectors', function () {
    expect(VectorMath::cosineSimilarity([1.0, 0.0], [0.0, 1.0]))->toBe(0.0);
});

test('cosineSimilarity is 0.0 when either vector is all zeros', function () {
    expect(VectorMath::cosineSimilarity([0.0, 0.0], [1.0, 1.0]))->toBe(0.0);
});

test('centroid averages component-wise', function () {
    expect(VectorMath::centroid([[1.0, 0.0], [0.0, 1.0]]))->toBe([0.5, 0.5]);
});

test('centroid of an empty list is empty', function () {
    expect(VectorMath::centroid([]))->toBe([]);
});
