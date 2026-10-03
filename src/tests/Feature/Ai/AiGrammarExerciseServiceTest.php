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

test('generate stores all five exercise types with their own fields', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'exercises' => [
            ['type' => 'multiple_choice', 'prompt' => 'I _____ this film twice.', 'options' => ['saw', 'have seen', 'seen'], 'answer_index' => 1, 'hint' => 'Twice = experience up to now.'],
            ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Have you ever been to Japan?', 'tiles' => ['Have', 'you', 'ever', 'been', 'to Japan?'], 'hint' => 'Questions start with the helper verb.'],
            ['type' => 'cloze', 'prompt' => 'She _____ (lose) her keys.', 'answer' => 'has lost', 'accepted_answers' => ["'s lost"], 'hint' => 'Irregular verb: think of its 3rd form.'],
            ['type' => 'transform', 'instruction' => 'Make it a question', 'prompt' => 'They have finished.', 'answer' => 'Have they finished?', 'hint' => 'Move the helper verb.'],
            ['type' => 'fix', 'prompt' => 'I have seen him yesterday.', 'answer' => 'I saw him yesterday.', 'hint' => '"Yesterday" is a finished time.'],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5, null, GrammarRuleExercise::ORIGIN_AI);

    $byType = $rule->exercises()->get()->keyBy('type');
    expect($created)->toBe(5)
        ->and($byType->keys()->sort()->values()->all())->toBe(['build', 'cloze', 'fix', 'multiple_choice', 'transform'])
        ->and($byType['build']->tiles)->toBe(['Have', 'you', 'ever', 'been', 'to Japan?'])
        ->and($byType['cloze']->accepted_answers)->toBe(["'s lost"])
        ->and($byType['transform']->instruction)->toBe('Make it a question')
        ->and($byType['multiple_choice']->hint)->toBe('Twice = experience up to now.')
        ->and($rule->exercises()->where('origin', GrammarRuleExercise::ORIGIN_AI)->count())->toBe(5);
});

test('generate drops broken typed exercises and hints that give the answer away', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'exercises' => [
            ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Have you been there?', 'tiles' => ['Have', 'you', 'gone', 'there?']], // tiles ≠ answer
            ['type' => 'fix', 'prompt' => 'I saw him yesterday.', 'answer' => 'I saw him yesterday.'], // nothing to fix
            ['type' => 'transform', 'prompt' => 'They have finished.', 'answer' => ''], // no answer
            ['type' => 'cloze', 'prompt' => 'She _____ (lose) her keys.', 'answer' => 'has lost', 'hint' => 'The answer is "has lost".'],
            ['type' => 'multiple_choice', 'prompt' => 'I _____ it.', 'options' => ['saw', 'have seen'], 'answer_index' => 1, 'hint' => 'Pick have seen.'],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5);

    expect($created)->toBe(2)
        ->and($rule->exercises()->pluck('type')->sort()->values()->all())->toBe(['cloze', 'multiple_choice'])
        ->and($rule->exercises()->whereNotNull('hint')->count())->toBe(0);
});

test('generate skips prompts already in the pool and tells the model not to repeat them', function () {
    $rule = makeGrammarRuleForExercises();
    $rule->exercises()->create([
        'type' => 'cloze', 'prompt' => 'She _____ (lose) her keys.', 'dedup_key' => 'she _____ (lose) her keys',
        'answer' => 'has lost', 'status' => GrammarRuleExercise::STATUS_PUBLISHED,
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()
        ->withArgs(fn (string $system) => str_contains($system, 'do not repeat') && str_contains($system, 'She _____ (lose) her keys.'))
        ->andReturn([
            'exercises' => [
                ['type' => 'cloze', 'prompt' => 'she _____ (lose) her keys', 'answer' => 'has lost'],
                ['type' => 'cloze', 'prompt' => 'We _____ (see) it.', 'answer' => 'have seen'],
                ['type' => 'cloze', 'prompt' => 'We _____ (see) it!', 'answer' => 'have seen'],
            ],
        ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5);

    expect($created)->toBe(1)->and($rule->exercises()->count())->toBe(2);
});

test('build exercises with the same generic prompt are kept apart by their sentence, and may omit the prompt', function () {
    $rule = makeGrammarRuleForExercises();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'exercises' => [
            ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Have you been to Japan?', 'tiles' => ['Have', 'you', 'been', 'to Japan?']],
            ['type' => 'build', 'prompt' => 'Make a question', 'answer' => 'Has she finished it?', 'tiles' => ['Has', 'she', 'finished', 'it?']],
            ['type' => 'build', 'instruction' => 'Make a question', 'answer' => 'Have they left yet?', 'tiles' => ['Have', 'they', 'left', 'yet?']],
            ['type' => 'build', 'prompt' => 'Ask about Japan', 'answer' => 'have you been to Japan', 'tiles' => ['have', 'you', 'been', 'to', 'Japan']],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarExerciseService::class)->generate($rule->id, 5);

    expect($created)->toBe(3)
        ->and($rule->exercises()->pluck('prompt')->all())->toBe(['Make a question', 'Make a question', 'Make a question']);
});
