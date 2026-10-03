<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\PromptTemplateAdminService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);
});

test('testRun() renders the templates with the given variables and returns the real response', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '  A short explanation.  ']]]], 200),
    ]);

    $result = app(PromptTemplateAdminService::class)->testRun(
        'You are a tutor for {{language}}.',
        'Explain "{{lexeme}}".',
        ['language' => 'English', 'lexeme' => 'run'],
        null,
    );

    expect($result->renderedSystem)->toBe('You are a tutor for English.')
        ->and($result->renderedUser)->toBe('Explain "run".')
        ->and($result->response)->toBe('A short explanation.');

    Http::assertSent(fn ($request) => $request['messages'][0]['content'] === 'You are a tutor for English.'
        && $request['messages'][1]['content'] === 'Explain "run".');
});

test('testRun() renders {{#if}} conditionals from the given variables', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    $result = app(PromptTemplateAdminService::class)->testRun(
        '{{#if strict}}Be strict.{{else}}Be lenient.{{/if}}',
        'x',
        ['strict' => 'yes'],
        null,
    );

    expect($result->renderedSystem)->toBe('Be strict.');
});

test('testRun() throws when a template references a variable not provided', function () {
    app(PromptTemplateAdminService::class)->testRun('Hello {{name}}.', 'x', [], null);
})->throws(InvalidArgumentException::class, 'undefined placeholder "{{name}}"');

test('testRun() sends the given model override to the real API call', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]], 200),
    ]);

    app(PromptTemplateAdminService::class)->testRun('sys', 'usr', [], 'gpt-4o');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o');
});

test('testRun() propagates AiClientException from the underlying call', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    app(PromptTemplateAdminService::class)->testRun('sys', 'usr', [], null);
})->throws(AiClientException::class);
