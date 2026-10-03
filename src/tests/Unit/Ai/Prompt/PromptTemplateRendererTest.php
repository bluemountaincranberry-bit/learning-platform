<?php

use App\Modules\Ai\Application\Prompt\PromptTemplateRenderer;

test('render() substitutes every placeholder with its variable', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('Explain "{{lexeme}}" in {{language}}.', [
        'lexeme' => 'run',
        'language' => 'English',
    ]);

    expect($result)->toBe('Explain "run" in English.');
});

test('render() leaves a template with no placeholders untouched', function () {
    $renderer = new PromptTemplateRenderer;

    expect($renderer->render('No placeholders here.', []))->toBe('No placeholders here.');
});

test('render() tolerates unused variables', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('Hello {{name}}.', ['name' => 'Alice', 'unused' => 'x']);

    expect($result)->toBe('Hello Alice.');
});

test('render() throws when a placeholder has no matching variable', function () {
    $renderer = new PromptTemplateRenderer;

    expect(fn () => $renderer->render('Explain {{lexeme}}.', []))
        ->toThrow(InvalidArgumentException::class, 'undefined placeholder "{{lexeme}}"');
});

test('render() substitutes the same placeholder repeated multiple times', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('{{word}} means {{word}}.', ['word' => 'foo']);

    expect($result)->toBe('foo means foo.');
});

test('render() keeps the if-branch of an {{#if}} block when the variable is non-empty', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('Base.{{#if extra}} Extra: {{extra}}.{{/if}}', ['extra' => 'more detail']);

    expect($result)->toBe('Base. Extra: more detail.');
});

test('render() drops an {{#if}} block with no {{else}} when the variable is empty', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('Base.{{#if extra}} Extra: {{extra}}.{{/if}} End.', ['extra' => '']);

    expect($result)->toBe('Base. End.');
});

test('render() uses the {{else}} branch when the variable is empty', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('{{#if thorough}}Be thorough.{{else}}Stay focused.{{/if}}', ['thorough' => '']);

    expect($result)->toBe('Stay focused.');
});

test('render() uses the if-branch over {{else}} when the variable is non-empty', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('{{#if thorough}}Be thorough.{{else}}Stay focused.{{/if}}', ['thorough' => 'yes']);

    expect($result)->toBe('Be thorough.');
});

test('render() treats a whitespace-only variable as falsy for {{#if}}', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('{{#if x}}yes{{else}}no{{/if}}', ['x' => '   ']);

    expect($result)->toBe('no');
});

test('render() throws when an {{#if}} condition variable is undefined', function () {
    $renderer = new PromptTemplateRenderer;

    expect(fn () => $renderer->render('{{#if missing}}x{{/if}}', []))
        ->toThrow(InvalidArgumentException::class, 'undefined {{#if missing}} condition variable');
});

test('render() does not require a placeholder that only appears in a dropped {{#if}} branch to exist', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render('Base.{{#if extra}} {{not_provided}}{{/if}}', ['extra' => '']);

    expect($result)->toBe('Base.');
});

test('render() supports multiple independent {{#if}} blocks in one template', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render(
        'A.{{#if x}} X!{{/if}}{{#if y}} Y!{{/if}}',
        ['x' => 'yes', 'y' => '']
    );

    expect($result)->toBe('A. X!');
});

test('render() combines {{#if}} blocks with normal placeholder substitution outside them', function () {
    $renderer = new PromptTemplateRenderer;

    $result = $renderer->render(
        'Hello {{name}}.{{#if note}} Note: {{note}}.{{/if}}',
        ['name' => 'Alice', 'note' => 'be nice']
    );

    expect($result)->toBe('Hello Alice. Note: be nice.');
});
