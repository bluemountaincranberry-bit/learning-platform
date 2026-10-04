<?php

use App\Contracts\Ai\AiJsonClient;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarRuleExampleGeneration;
use App\Modules\Content\Domain\Models\GrammarTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.enabled' => true, 'ai.openai.api_key' => 'test-key']);
});

function makeRuleForBackfill(string $status = GrammarRule::STATUS_PUBLISHED, int $examples = 0): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-bf-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-bf-'.uniqid(),
        'language' => 'en',
        'title' => 'Rule '.uniqid(),
        'status' => $status,
    ]);
    for ($i = 1; $i <= $examples; $i++) {
        $rule->examples()->create(['language' => 'en', 'example' => "Existing sentence {$i}.", 'sort_order' => $i * 10]);
    }

    return $rule;
}

/** Fake AI that answers each call with `$count` distinct marked examples. */
function fakeBackfillAi(int $count = 8): void
{
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function () use ($count): array {
        static $call = 0;
        $call++;
        $kinds = ['affirmative', 'negative', 'question', 'mistake'];

        return ['examples' => array_map(fn (int $i): array => [
            'text' => "Call {$call} line {$i} she **works**.",
            'kind' => $kinds[$i % 4],
            'wrong' => "Call {$call} line {$i} she work.",
            'translation' => 'т',
        ], range(1, $count))];
    });
    app()->instance(AiJsonClient::class, $client);
}

test('backfill tops every non-archived rule below the minimum up to the target', function () {
    $empty = makeRuleForBackfill();
    $few = makeRuleForBackfill(examples: 2);
    $enough = makeRuleForBackfill(examples: 6);
    $draft = makeRuleForBackfill(GrammarRule::STATUS_DRAFT);
    $archived = makeRuleForBackfill(GrammarRule::STATUS_ARCHIVED);
    fakeBackfillAi();

    $this->artisan('grammar:backfill-examples', ['--sync' => true])->assertExitCode(0);

    expect($empty->examples()->count())->toBe(8)
        ->and($few->examples()->count())->toBe(8)
        ->and($enough->examples()->count())->toBe(6)
        ->and($draft->examples()->count())->toBe(8)
        ->and($archived->examples()->count())->toBe(0)
        ->and(GrammarRuleExampleGeneration::query()->whereNull('user_id')->count())->toBe(3)
        ->and($empty->examples()->first()->translation_language)->toBe('ru');
});

test('backfill retries a short batch once and fails loudly if the rule is still below the minimum', function () {
    $rule = makeRuleForBackfill();
    fakeBackfillAi(2);

    $this->artisan('grammar:backfill-examples', ['--sync' => true])->assertExitCode(1);

    expect($rule->examples()->count())->toBe(4)
        ->and(GrammarRuleExampleGeneration::query()->where('grammar_rule_id', $rule->id)->count())->toBe(2);
});

test('backfill retry fills a short first batch', function () {
    $rule = makeRuleForBackfill();
    fakeBackfillAi(4);

    $this->artisan('grammar:backfill-examples', ['--sync' => true])->assertExitCode(0);

    expect($rule->examples()->count())->toBe(8);
});

test('backfill dry run lists rules without calling the AI', function () {
    makeRuleForBackfill(examples: 1);
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    app()->instance(AiJsonClient::class, $client);

    $this->artisan('grammar:backfill-examples', ['--dry-run' => true])->assertExitCode(0);

    expect(GrammarRuleExampleGeneration::query()->count())->toBe(0);
});

test('backfill queues batches by default', function () {
    makeRuleForBackfill();
    Illuminate\Support\Facades\Queue::fake();

    $this->artisan('grammar:backfill-examples')->assertExitCode(0);

    Illuminate\Support\Facades\Queue::assertPushed(\App\Modules\Ai\Interfaces\Jobs\GenerateGrammarRuleExamplesJob::class, 1);
});

test('grammar_examples eval passes when examples use each rule and leaves no rows behind', function () {
    $answers = [
        'Present Perfect' => ['She **has lived** here.', 'He **hasn\'t seen** it.', '**Have** you **been** there?'],
        'Present Continuous' => ['She is **working** now.', 'He isn\'t **sleeping**.', 'Are you **coming**?'],
        'First Conditional' => ['If it rains, we **will stay** home.', 'If you go, I **won\'t** wait.', 'Will you call **if** you can?'],
        'Passive Voice (present and past simple)' => ['The bridge **was built** in 1900.', 'It **isn\'t made** here.', '**Is** it **sold** online?'],
        'Reported speech' => ['She said she **was** tired.', 'He told me he **didn\'t** know.', 'She asked **if** I **was** ready.'],
        'Can for ability' => ['She **can** swim.', 'He **can\'t** drive.', '**Can** you cook?'],
    ];
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function (string $system) use ($answers): array {
        foreach ($answers as $title => $sentences) {
            if (str_contains($system, "\"{$title}\"")) {
                $kinds = ['affirmative', 'negative', 'question'];
                $items = [];
                for ($i = 0; $i < 6; $i++) {
                    $items[] = ['text' => "{$i}: ".$sentences[$i % 3], 'kind' => $kinds[$i % 3], 'translation' => 'т'];
                }

                return ['examples' => $items];
            }
        }

        return ['examples' => []];
    });
    app()->instance(AiJsonClient::class, $client);
    $rulesBefore = GrammarRule::query()->count();

    $this->artisan('ai:eval', ['--suite' => 'grammar_examples'])
        ->expectsTable(
            ['case', 'examples', 'uses rule', 'form marked', 'marked+translated', 'kinds', 'result'],
            [
                ['present-perfect', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
                ['present-continuous', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
                ['first-conditional', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
                ['passive-voice', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
                ['reported-speech', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
                ['can-for-ability', '6', '6/6', '6/6', '6/6', 'all', 'pass'],
            ]
        )
        ->assertExitCode(0);

    expect(GrammarRule::query()->count())->toBe($rulesBefore)
        ->and(GrammarRuleExample::query()->count())->toBe(0);
});

test('grammar_examples eval fails when a mistake pair is stored the wrong way round', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(function (string $system): array {
        $items = array_map(fn (int $i): array => ['text' => "{$i} If it rains, we **will stay**.", 'kind' => ['affirmative', 'negative', 'question'][$i % 3], 'translation' => 'т'], range(1, 6));
        if (str_contains($system, '"First Conditional"')) {
            $items[] = ['text' => 'If it **will rain**, we will stay.', 'kind' => 'mistake', 'wrong' => 'If it rains, we will stay.', 'translation' => 'т'];
        }

        return ['examples' => $items];
    });
    app()->instance(AiJsonClient::class, $client);

    $this->artisan('ai:eval', ['--suite' => 'grammar_examples'])
        ->expectsOutputToContain('[mistake] If it will rain, we will stay.')
        ->assertExitCode(1);
});

test('grammar_examples eval fails when examples drift off the rule', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturnUsing(fn (): array => ['examples' => array_map(
        fn (int $i): array => ['text' => "{$i} She **likes** tea.", 'kind' => ['affirmative', 'negative', 'question'][$i % 3], 'translation' => 'т'],
        range(1, 6)
    )]);
    app()->instance(AiJsonClient::class, $client);

    $this->artisan('ai:eval', ['--suite' => 'grammar_examples'])
        ->expectsOutputToContain('present-perfect: examples off the rubric')
        ->expectsOutputToContain('She likes tea. (marked: likes)')
        ->assertExitCode(1);
});

test('editing an example sentence drops its stale marking', function () {
    $rule = makeRuleForBackfill();
    $example = $rule->examples()->create(['language' => 'en', 'example' => 'She works.', 'target_spans' => [[4, 9]], 'sort_order' => 10]);

    $example->update(['translation' => 'Она работает.']);
    expect($example->fresh()->target_spans)->toBe([[4, 9]]);

    $example->update(['example' => 'She often works.']);
    expect($example->fresh()->target_spans)->toBeNull();
});

test('admin examples sync keeps the row, hides and AI marking of unchanged sentences', function () {
    $rule = makeRuleForBackfill();
    $kept = $rule->examples()->create([
        'language' => 'en', 'example' => 'She works.', 'origin' => 'ai', 'kind' => 'affirmative',
        'target_spans' => [[4, 9]], 'sort_order' => 10,
    ]);
    $dropped = $rule->examples()->create(['language' => 'en', 'example' => 'Old one.', 'sort_order' => 20]);
    \App\Modules\Content\Domain\Models\GrammarRuleExampleHide::query()->create(['user_id' => \App\Modules\User\Models\User::factory()->create()->id, 'grammar_rule_example_id' => $kept->id]);

    app(\App\Modules\Content\Application\Contracts\GrammarCatalogServiceInterface::class)->updateRule($rule->fresh(), [
        'examples' => [
            ['example' => 'She works.', 'translation' => 'Она работает.'],
            ['example' => 'New admin sentence.'],
        ],
    ]);

    $examples = $rule->examples()->get();
    expect($examples[0]->id)->toBe($kept->id)
        ->and(\App\Modules\Content\Domain\Models\GrammarRuleExampleHide::query()->count())->toBe(1)
        ->and($examples[0]->origin)->toBe('ai')
        ->and($examples[0]->target_spans)->toBe([[4, 9]])
        ->and($examples[0]->translation)->toBe('Она работает.')
        ->and($examples[1]->origin)->toBe('admin')
        ->and($examples[1]->target_spans)->toBeNull()
        ->and($examples)->toHaveCount(2)
        ->and($dropped->fresh())->toBeNull();
});
