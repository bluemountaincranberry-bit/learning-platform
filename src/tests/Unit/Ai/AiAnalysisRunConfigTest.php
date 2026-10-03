<?php

use App\Contracts\Ai\AiAnalysisRunConfig;

test('fromArray parses comma-separated exclude_words', function () {
    $config = AiAnalysisRunConfig::fromArray(['exclude_words' => 'run, jump , eat']);

    expect($config->excludeWords)->toBe(['run', 'jump', 'eat']);
});

test('fromArray parses newline-separated exclude_words', function () {
    $config = AiAnalysisRunConfig::fromArray(['exclude_words' => "run\njump\n\neat"]);

    expect($config->excludeWords)->toBe(['run', 'jump', 'eat']);
});

test('fromArray accepts exclude_words already as an array', function () {
    $config = AiAnalysisRunConfig::fromArray(['exclude_words' => ['run', ' jump ']]);

    expect($config->excludeWords)->toBe(['run', 'jump']);
});

test('fromArray defaults invalid thoroughness to focused', function () {
    $config = AiAnalysisRunConfig::fromArray(['thoroughness' => 'bogus']);

    expect($config->thoroughness)->toBe(AiAnalysisRunConfig::THOROUGHNESS_FOCUSED);
});

test('fromArray accepts thorough', function () {
    $config = AiAnalysisRunConfig::fromArray(['thoroughness' => 'thorough']);

    expect($config->thoroughness)->toBe(AiAnalysisRunConfig::THOROUGHNESS_THOROUGH);
});

test('fromArray casts exclude_grammar_rule_ids to ints and drops empty values', function () {
    $config = AiAnalysisRunConfig::fromArray(['exclude_grammar_rule_ids' => ['3', '', '7', null]]);

    expect($config->excludeGrammarRuleIds)->toBe([3, 7]);
});

test('fromArray treats blank target_level and extra_instructions as null', function () {
    $config = AiAnalysisRunConfig::fromArray(['target_level' => '', 'extra_instructions' => '   ']);

    expect($config->targetLevel)->toBeNull()
        ->and($config->extraInstructions)->toBeNull();
});

test('fromArray with empty data returns all defaults', function () {
    $config = AiAnalysisRunConfig::fromArray([]);

    expect($config->targetLevel)->toBeNull()
        ->and($config->excludeWords)->toBe([])
        ->and($config->excludeGrammarRuleIds)->toBe([])
        ->and($config->extraInstructions)->toBeNull()
        ->and($config->thoroughness)->toBe(AiAnalysisRunConfig::THOROUGHNESS_FOCUSED);
});

test('toArray round-trips through fromArray', function () {
    $original = AiAnalysisRunConfig::fromArray([
        'target_level' => 'B1',
        'exclude_words' => ['run', 'jump'],
        'exclude_grammar_rule_ids' => [1, 2],
        'extra_instructions' => 'Focus on idioms.',
        'thoroughness' => 'thorough',
        'translation_language' => 'fr',
    ]);

    $roundTripped = AiAnalysisRunConfig::fromArray($original->toArray());

    expect($roundTripped)->toEqual($original);
});

test('fromArray treats a blank translation_language as null', function () {
    $config = AiAnalysisRunConfig::fromArray(['translation_language' => '']);

    expect($config->translationLanguage)->toBeNull();
});

test('fromArray keeps a valid translation_language', function () {
    $config = AiAnalysisRunConfig::fromArray(['translation_language' => 'de']);

    expect($config->translationLanguage)->toBe('de');
});
