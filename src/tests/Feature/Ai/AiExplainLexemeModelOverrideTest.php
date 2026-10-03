<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\Ai\Application\AiExplainLexemeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * End-to-end proof that a prompt_template_versions.model override actually
 * reaches the real HTTP call — RenderedPrompt::$model existed since the
 * prompt registry was built but nothing consumed it until
 * AiExplainLexemeService::explain() started passing it to
 * AiClientInterface::complete() (task: per-model pricing groundwork).
 */
test('a published prompt template version with a model override changes which model the real API call uses', function () {
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);

    $template = PromptTemplate::query()->create(['key' => 'ai_explain_lexeme', 'name' => 'Explain lexeme']);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'You are a tutor.',
        'user_template' => 'Explain {{lexeme}}.',
        'model' => 'gpt-4o',
    ]);
    $template->update(['active_version_id' => $version->id]);

    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'An explanation.']]]], 200),
    ]);

    app(AiExplainLexemeService::class)->explain('run', 'English');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o');
});

test('without a model override, explain() still sends the provider default model', function () {
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key']);

    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'An explanation.']]]], 200),
    ]);

    app(AiExplainLexemeService::class)->explain('run', 'English');

    Http::assertSent(fn ($request) => $request['model'] === 'gpt-4o-mini');
});
