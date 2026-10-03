<?php

use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Contracts\Ai\ChatAiServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('a published chat_context_system_prompt override replaces the default system prompt in the real API call', function () {
    config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'test-key', 'ai.semantic_cache.enabled' => false]);

    $template = PromptTemplate::query()->create(['key' => 'chat_context_system_prompt', 'name' => 'Chat system prompt']);
    $version = $template->versions()->create([
        'version' => 1,
        'system_template' => 'OVERRIDDEN CHAT SYSTEM PROMPT',
        'user_template' => '',
    ]);
    $template->update(['active_version_id' => $version->id]);

    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Hi!']]]], 200),
    ]);

    app(ChatAiServiceInterface::class)->reply([], 'hello');

    Http::assertSent(fn ($request) => $request['messages'][0]['content'] === 'OVERRIDDEN CHAT SYSTEM PROMPT');
});
