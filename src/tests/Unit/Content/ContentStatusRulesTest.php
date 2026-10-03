<?php

use App\Modules\Content\Rules\ContentStatusRules;

test('content status rules define publication and allowed transitions', function () {
    $rules = new ContentStatusRules;

    expect($rules->isPublic('ready'))->toBeTrue()
        ->and($rules->isPublic('processing'))->toBeFalse()
        ->and($rules->canTransition('draft', 'pending'))->toBeTrue()
        ->and($rules->canTransition('draft', 'ready'))->toBeFalse()
        ->and($rules->isValidStatus('unknown'))->toBeFalse();
});
