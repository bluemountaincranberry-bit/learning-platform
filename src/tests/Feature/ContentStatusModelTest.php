<?php

use App\Modules\Content\Domain\Models\Content;

test('content aggregate is owned by the Content module', function () {
    expect((new Content)->getTable())->toBe('contents');
});

test('content exposes agreed status transitions for ingestion flow', function () {
    expect(Content::STATUS_TRANSITIONS)->toBe([
        'draft' => ['pending', 'rejected'],
        'pending' => ['processing', 'failed', 'rejected'],
        'processing' => ['ready', 'failed'],
        'ready' => ['rejected'],
        'rejected' => ['pending'],
        'failed' => ['pending', 'rejected'],
    ]);
});

test('content can tell whether a transition is allowed', function () {
    $content = new Content(['status' => 'pending']);

    expect($content->canTransitionTo('processing'))->toBeTrue()
        ->and($content->canTransitionTo('ready'))->toBeFalse()
        ->and($content->canTransitionTo('draft'))->toBeFalse();
});

test('content marks only ready status as publicly visible', function () {
    $ready = new Content(['status' => 'ready']);
    $pending = new Content(['status' => 'pending']);
    $failed = new Content(['status' => 'failed']);

    expect($ready->isPubliclyVisible())->toBeTrue()
        ->and($pending->isPubliclyVisible())->toBeFalse()
        ->and($failed->isPubliclyVisible())->toBeFalse();
});
