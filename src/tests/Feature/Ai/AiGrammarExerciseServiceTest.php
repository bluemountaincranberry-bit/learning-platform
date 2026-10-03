<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiGrammarExerciseService;
use App\Contracts\Ai\AiJsonClient;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExercise;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGrammarRuleForExercises(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-ex-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-ex-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'summary' => 'Past action, present relevance.',
        'body' => '## Rule',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

test('generate persists valid cloze and multiple_choice exercises as drafts', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'exercises' => [
            ['type' => 'cloze', 'prompt' => 'She _____ here for five years.', 'answer' => 'has lived', 'explanation' => 'Present perfect.'],
            ['type' => 'multiple_choice', 'prompt' => 'Choose the correct form', 'options' => ['has lived', 'live', 'living'], 'answer_index' => 0, 'explanation' => 'Present perfect.'],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5);

    expect($created)->toBe(2)
        ->and($rule->exercises()->count())->toBe(2)
        ->and($rule->exercises()->where('status', GrammarRuleExercise::STATUS_DRAFT)->count())->toBe(2);
});

test('generate skips malformed items and keeps the valid ones', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'exercises' => [
            ['type' => 'cloze', 'prompt' => '', 'answer' => 'x'], // empty prompt
            ['type' => 'cloze', 'prompt' => 'A sentence.', 'answer' => ''], // empty answer
            ['type' => 'multiple_choice', 'prompt' => 'Q', 'options' => ['only one']], // too few options
            ['type' => 'unknown', 'prompt' => 'Q', 'answer' => 'x'], // bad type
            ['type' => 'cloze', 'prompt' => 'Valid one.', 'answer' => 'yes'],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5);

    expect($created)->toBe(1)
        ->and($rule->exercises()->first()->prompt)->toBe('Valid one.');
});

test('generate throws when the AI returns no usable exercises', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['exercises' => []]);
    app()->instance(AiJsonClient::class, $client);

    expect(fn () => app(AiGrammarExerciseService::class)->generate($rule->id, 5))
        ->toThrow(AiClientException::class);
});

test('generate propagates AiClientException from the client', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new AiClientException('Rate limited'));
    app()->instance(AiJsonClient::class, $client);

    expect(fn () => app(AiGrammarExerciseService::class)->generate($rule->id, 5))
        ->toThrow(AiClientException::class, 'Rate limited');
});
