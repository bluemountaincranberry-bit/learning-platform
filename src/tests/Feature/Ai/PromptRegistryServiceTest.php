<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\Data\RenderedPrompt;
use App\Contracts\Ai\PromptRegistryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resolve() falls back to the caller default when no prompt_templates row exists for the key', function () {
    $registry = app(PromptRegistryInterface::class);

    $result = $registry->resolve('unknown_key', [], fn () => ['system' => 'default system', 'user' => 'default user']);

    expect($result)->toBeInstanceOf(RenderedPrompt::class)
        ->and($result->system)->toBe('default system')
        ->and($result->user)->toBe('default user')
        ->and($result->isOverride)->toBeFalse()
        ->and($result->model)->toBeNull();
});

test('resolve() falls back to the caller default when a prompt_templates row exists but has no active_version_id', function () {
    PromptTemplate::query()->create(['key' => 'draft_only', 'name' => 'Draft only']);

    $registry = app(PromptRegistryInterface::class);
    $result = $registry->resolve('draft_only', [], fn () => ['system' => 'default system', 'user' => 'default user']);

    expect($result->isOverride)->toBeFalse()
        ->and($result->system)->toBe('default system');
});

test('resolve() renders the active version with variables when one is published', function () {
    $template = PromptTemplate::query()->create(['key' => 'ai_explain_lexeme', 'name' => 'Explain lexeme']);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'You are a tutor for {{language}}.',
        'user_template' => 'Explain "{{lexeme}}".',
        'model' => 'gpt-4o-mini',
    ]);
    $template->update(['active_version_id' => $version->id]);

    $registry = app(PromptRegistryInterface::class);
    $result = $registry->resolve(
        'ai_explain_lexeme',
        ['lexeme' => 'run', 'language' => 'English'],
        fn () => ['system' => 'unused default', 'user' => 'unused default']
    );

    expect($result->isOverride)->toBeTrue()
        ->and($result->system)->toBe('You are a tutor for English.')
        ->and($result->user)->toBe('Explain "run".')
        ->and($result->model)->toBe('gpt-4o-mini');
});

test('resolve() never calls the default closure when an active version exists', function () {
    $template = PromptTemplate::query()->create(['key' => 'k', 'name' => 'K']);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'sys',
        'user_template' => 'usr',
    ]);
    $template->update(['active_version_id' => $version->id]);

    $registry = app(PromptRegistryInterface::class);
    $registry->resolve('k', [], function () {
        throw new RuntimeException('default should not be called when an override is active');
    });
})->throwsNoExceptions();

test('publishing a new version switches which text resolve() returns, old versions stay intact', function () {
    $template = PromptTemplate::query()->create(['key' => 'k', 'name' => 'K']);
    $v1 = $template->versions()->create(['version' => 1, 'system_template' => 'sys v1', 'user_template' => 'usr v1']);
    $template->update(['active_version_id' => $v1->id]);

    $registry = app(PromptRegistryInterface::class);
    expect($registry->resolve('k', [], fn () => ['system' => '', 'user' => ''])->system)->toBe('sys v1');

    $v2 = $template->versions()->create(['version' => 2, 'system_template' => 'sys v2', 'user_template' => 'usr v2']);
    $template->update(['active_version_id' => $v2->id]);

    expect($registry->resolve('k', [], fn () => ['system' => '', 'user' => ''])->system)->toBe('sys v2')
        ->and($v1->fresh()->system_template)->toBe('sys v1');
});
