<?php

use App\Modules\Content\Application\GrammarAnswerChecker;

test('typed answers ignore case, outer spaces and final punctuation', function (string $given) {
    expect((new GrammarAnswerChecker)->typedMatches($given, 'Have they finished the report?', []))->toBeTrue();
})->with([
    'exact' => 'Have they finished the report?',
    'lower case, no question mark' => 'have they finished the report',
    'extra spaces' => '  Have   they finished  the report ?  ',
    'full stop instead' => 'Have they finished the report.',
]);

test('contractions equal their full forms in both directions', function (string $given, string $answer) {
    expect((new GrammarAnswerChecker)->typedMatches($given, $answer, []))->toBeTrue();
})->with([
    ["haven't seen", 'have not seen'],
    ['have not seen', "haven't seen"],
    ['She’s lost her keys.', 'She has lost her keys.'],
    ["She's lost her keys.", 'She has lost her keys.'],
    ["He's tired", 'He is tired'],
    ["I've finished", 'I have finished'],
    ["I won't go", 'I will not go'],
    ["I can't swim", 'I cannot swim'],
    ['I can not swim', 'I cannot swim'],
    ["They'd left", 'They had left'],
    ["We're here", 'We are here'],
    ["I'm late", 'I am late'],
    ["You'll see", 'You will see'],
]);

test('accepted answers count as correct', function () {
    $checker = new GrammarAnswerChecker;

    expect($checker->typedMatches('I saw him yesterday', 'I saw him yesterday.', []))->toBeTrue()
        ->and($checker->typedMatches('Yesterday I saw him', 'I saw him yesterday.', ['Yesterday I saw him.']))->toBeTrue()
        ->and($checker->typedMatches('I have seen him yesterday', 'I saw him yesterday.', ['Yesterday I saw him.']))->toBeFalse();
});

test('a wrong form never matches', function () {
    $checker = new GrammarAnswerChecker;

    expect($checker->typedMatches('has losed', 'has lost', []))->toBeFalse()
        ->and($checker->typedMatches('', 'has lost', []))->toBeFalse()
        ->and($checker->typedMatches("she's", 'she was', []))->toBeFalse();
});

test('hint leaks the answer when it contains the normalized answer', function () {
    $checker = new GrammarAnswerChecker;

    expect($checker->hintRevealsAnswer('The answer is has lost.', 'has lost'))->toBeTrue()
        ->and($checker->hintRevealsAnswer("Try: hasn't lost", 'has not lost'))->toBeTrue()
        ->and($checker->hintRevealsAnswer('Irregular verb: think of its 3rd form.', 'has lost'))->toBeFalse()
        ->and($checker->hintRevealsAnswer('Use have + past participle.', 'have'))->toBeTrue();
});
