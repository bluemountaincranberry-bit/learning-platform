<?php

use App\Contracts\Ai\AiJsonClient;
use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiGrammarRuleExampleService;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGrammarRuleForExamples(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-exm-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-exm-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Simple',
        'summary' => 'Habits and facts.',
        'body' => '## Rule',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

function fakeExampleClient(array $examples, ?callable $inspect = null): void
{
    $client = Mockery::mock(AiJsonClient::class);
    $expectation = $client->shouldReceive('completeJson')->once();
    if ($inspect !== null) {
        $expectation->withArgs(function (...$args) use ($inspect): bool {
            $inspect(...$args);

            return true;
        });
    }
    $expectation->andReturn(['examples' => $examples]);
    app()->instance(AiJsonClient::class, $client);
}

test('generate stores AI examples with plain text, marked target spans, kind and translation', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'She **works** in a bank.', 'kind' => 'affirmative', 'translation' => 'Она работает в банке.'],
        ['text' => 'He **doesn\'t like** tea.', 'kind' => 'negative', 'translation' => 'Он не любит чай.'],
        ['text' => '**Do** you **live** here?', 'kind' => 'question', 'translation' => 'Ты живёшь здесь?'],
    ]);

    $created = app(AiGrammarRuleExampleService::class)->generate($rule->id, 3, 'ru');

    expect($created)->toBe(3);
    $examples = GrammarRuleExample::query()->where('grammar_rule_id', $rule->id)->orderBy('sort_order')->get();

    expect($examples->pluck('example')->all())->toBe(['She works in a bank.', 'He doesn\'t like tea.', 'Do you live here?'])
        ->and($examples[0]->target_spans)->toBe([[4, 9]])
        ->and($examples[1]->target_spans)->toBe([[3, 15]])
        ->and($examples[2]->target_spans)->toBe([[0, 2], [7, 11]])
        ->and($examples->pluck('kind')->all())->toBe(['affirmative', 'negative', 'question'])
        ->and($examples->pluck('origin')->unique()->all())->toBe([GrammarRuleExample::ORIGIN_AI])
        ->and($examples[0]->translation)->toBe('Она работает в банке.')
        ->and($examples[0]->translation_language)->toBe('ru');
});

test('target spans are counted in characters, not bytes', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'Café owners **open** early.', 'kind' => 'affirmative', 'translation' => 'т'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 1, 'ru');

    $example = GrammarRuleExample::query()->where('grammar_rule_id', $rule->id)->first();
    expect(mb_substr($example->example, 12, 4))->toBe('open')
        ->and($example->target_spans)->toBe([[12, 16]]);
});

test('a mistake example keeps the typical wrong sentence next to the correct one', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'She **doesn\'t** like coffee.', 'kind' => 'mistake', 'wrong' => 'She don\'t like coffee.', 'translation' => 'Она не любит кофе.'],
        ['text' => 'She **goes** to work.', 'kind' => 'mistake', 'translation' => 'т'], // mistake kind without the wrong sentence → becomes a plain example
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 2, 'ru');

    $examples = GrammarRuleExample::query()->where('grammar_rule_id', $rule->id)->orderBy('sort_order')->get();
    expect($examples[0]->kind)->toBe('mistake')
        ->and($examples[0]->mistake)->toBe('She don\'t like coffee.')
        ->and($examples[1]->kind)->toBeNull()
        ->and($examples[1]->mistake)->toBeNull();
});

test('the mistakes list is stored after the examples with the fixed sentence as the example', function () {
    $rule = makeGrammarRuleForExamples();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'examples' => [
            ['text' => 'If it **rains**, we **will stay**.', 'kind' => 'affirmative', 'translation' => 'Если пойдёт дождь, мы останемся.'],
        ],
        'mistakes' => [
            ['wrong' => 'If it will snow, we will ski.', 'correct' => 'If it **snows**, we **will ski**.', 'translation' => 'Если пойдёт снег, мы покатаемся.'],
            ['wrong' => '', 'correct' => 'If you **go**, I **will go**.', 'translation' => 'т'], // no error given → plain example
            'junk',
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $created = app(AiGrammarRuleExampleService::class)->generate($rule->id, 5, 'ru');

    $examples = $rule->examples()->get();
    expect($created)->toBe(3)
        ->and($examples[1]->example)->toBe('If it snows, we will ski.')
        ->and($examples[1]->kind)->toBe('mistake')
        ->and($examples[1]->mistake)->toBe('If it will snow, we will ski.')
        ->and($examples[1]->translation)->toBe('Если пойдёт снег, мы покатаемся.')
        ->and($examples[2]->kind)->toBeNull();
});

test('mistakes go in before surplus examples, which refill dropped ones', function () {
    $rule = makeGrammarRuleForExamples();
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'examples' => [
            ['text' => 'A **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
            ['text' => 'not marked', 'kind' => 'affirmative', 'translation' => 'т'],
            ['text' => 'B **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
            ['text' => 'C **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ],
        'mistakes' => [
            ['wrong' => 'D work.', 'correct' => 'D **works**.', 'translation' => 'т'],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 3, 'ru');

    // head = 3 - 1 mistake = 2 examples (one unmarked → dropped), the mistake, then B from the surplus.
    expect($rule->examples()->pluck('example')->all())->toBe(['A works.', 'D works.', 'B works.']);
});

test('cutting to the requested count keeps every kind the model wrote', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'A **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'B **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'C **works**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'D **doesn\'t work**.', 'kind' => 'negative', 'translation' => 'т'],
        ['text' => '**Does** E **work**?', 'kind' => 'question', 'translation' => 'т'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 3, 'ru');

    expect($rule->examples()->pluck('kind')->all())->toBe(['affirmative', 'negative', 'question']);
});

test('markers are removed from the mistake and the translation', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'She wants **a** dress.', 'kind' => 'mistake', 'wrong' => 'She wants **the** dress.', 'translation' => 'Она хочет **какое-нибудь** платье.'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 1, 'ru');

    $example = $rule->examples()->first();
    expect($example->mistake)->toBe('She wants the dress.')
        ->and($example->translation)->toBe('Она хочет какое-нибудь платье.');
});

test('a mistake identical to the correct sentence is not a mistake example', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'She **works** here.', 'kind' => 'mistake', 'wrong' => 'She works here!', 'translation' => 'т'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 1, 'ru');

    $example = $rule->examples()->first();
    expect($example->kind)->toBeNull()
        ->and($example->mistake)->toBeNull();
});

test('generate drops unmarked, untranslated, empty, overlong and duplicate examples', function () {
    $rule = makeGrammarRuleForExamples();
    $rule->examples()->create(['language' => 'en', 'example' => 'I work from home.', 'sort_order' => 10]);

    fakeExampleClient([
        ['text' => 'No marked form here.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => '', 'kind' => 'affirmative'],
        ['text' => '**'.str_repeat('a', 300).'**', 'kind' => 'affirmative'],
        ['text' => 'I **work** from home!', 'kind' => 'affirmative', 'translation' => 'т'], // duplicate of existing (case/punctuation)
        ['text' => 'We **cook** often.', 'kind' => 'affirmative'], // translation asked for but missing
        ['text' => 'They **play** football.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'They **play** football', 'kind' => 'affirmative', 'translation' => 'т'], // duplicate within the batch
        ['text' => '**Unclosed marker.', 'kind' => 'affirmative'],
        'not an array',
    ]);

    $created = app(AiGrammarRuleExampleService::class)->generate($rule->id, 8, 'ru');

    expect($created)->toBe(1)
        ->and($rule->examples()->pluck('example')->all())->toBe(['I work from home.', 'They play football.']);
});

test('generate appends after existing examples and never touches them', function () {
    $rule = makeGrammarRuleForExamples();
    $existing = $rule->examples()->create(['language' => 'en', 'example' => 'I work.', 'translation' => 'Я работаю.', 'sort_order' => 50]);
    fakeExampleClient([
        ['text' => 'We **cook** dinner.', 'kind' => 'affirmative', 'translation' => 'т'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 1, 'ru');

    expect($existing->fresh()->origin)->toBe(GrammarRuleExample::ORIGIN_ADMIN)
        ->and($rule->examples()->where('origin', GrammarRuleExample::ORIGIN_AI)->first()->sort_order)->toBeGreaterThan(50);
});

test('generate keeps at most the requested count', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'A **runs**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'B **runs**.', 'kind' => 'affirmative', 'translation' => 'т'],
        ['text' => 'C **runs**.', 'kind' => 'affirmative', 'translation' => 'т'],
    ]);

    expect(app(AiGrammarRuleExampleService::class)->generate($rule->id, 2, 'ru'))->toBe(2);
});

test('the prompt names the rule, level, translation language and existing examples to avoid', function () {
    $rule = makeGrammarRuleForExamples();
    $rule->update(['level' => 'A2']);
    $rule->examples()->create(['language' => 'en', 'example' => 'I work from home.', 'sort_order' => 10]);

    $system = null;
    fakeExampleClient(
        [['text' => 'She **works**.', 'kind' => 'affirmative', 'translation' => 'т']],
        function ($systemPrompt) use (&$system): void {
            $system = $systemPrompt;
        }
    );

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 4, 'ru');

    expect($system)->toContain('Present Simple')
        ->toContain('A2')
        ->toContain('"ru"')
        ->toContain('I work from home.')
        ->toContain('**');
});

test('without a translation language examples are stored without translation', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([
        ['text' => 'She **works**.', 'kind' => 'affirmative', 'translation' => 'Она работает.'],
    ]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 1, null);

    $example = $rule->examples()->first();
    expect($example->translation)->toBeNull()
        ->and($example->translation_language)->toBeNull();
});

test('generate throws when the AI returns no usable examples', function () {
    $rule = makeGrammarRuleForExamples();
    fakeExampleClient([['text' => 'nothing marked', 'kind' => 'affirmative']]);

    app(AiGrammarRuleExampleService::class)->generate($rule->id, 3, 'ru');
})->throws(AiClientException::class);
