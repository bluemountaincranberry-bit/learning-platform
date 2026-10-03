<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Ai\Application\Agent\Tracing\TracedLlmCall;
use App\Modules\Ai\Application\AiFieldEditService;
use App\Contracts\Ai\AiEditablePrompt;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('propose calls the AI client with the builders prompt and schema and returns the result verbatim', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft']);

    $builder = Mockery::mock(AiEditablePrompt::class);
    $builder->shouldReceive('buildPrompt')
        ->once()
        ->with($rule, 'make it shorter')
        ->andReturn(['system' => 'sys', 'user' => 'usr', 'schema' => ['body' => 'string']]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')
        ->once()
        ->with('sys', 'usr', ['body' => 'string'], null)
        ->andReturn(['body' => 'Shorter body.']);

    $result = (new AiFieldEditService($client, app(TracedLlmCall::class)))->propose($rule, $builder, 'make it shorter');

    expect($result)->toBe(['body' => 'Shorter body.']);
});

test('propose does not touch the database', function () {
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft', 'body' => null]);

    $builder = Mockery::mock(AiEditablePrompt::class);
    $builder->shouldReceive('buildPrompt')->once()->andReturn(['system' => 's', 'user' => 'u', 'schema' => []]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['body' => 'Proposed body.']);

    (new AiFieldEditService($client, app(TracedLlmCall::class)))->propose($rule, $builder, null);

    expect($rule->fresh()->body)->toBeNull();
});
