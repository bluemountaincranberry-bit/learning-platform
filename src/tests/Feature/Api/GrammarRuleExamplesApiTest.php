<?php

use App\Contracts\Ai\AiJsonClient;
use App\Contracts\Ai\GrammarRuleExampleGenerationDispatcher;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarRuleExample;
use App\Modules\Content\Domain\Models\GrammarRuleExampleGeneration;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['ai.enabled' => true]);
});

function makeRuleForExamplesApi(string $status = GrammarRule::STATUS_PUBLISHED): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-exapi-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-exapi-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Simple',
        'summary' => 'Habits.',
        'status' => $status,
    ]);
}

function fakeExamplesAi(int $times = 1): void
{
    $client = Mockery::mock(AiJsonClient::class);
    $calls = 0;
    $client->shouldReceive('completeJson')->times($times)->andReturnUsing(function () use (&$calls): array {
        $calls++;

        return ['examples' => [
            ['text' => "Batch {$calls} she **works** late.", 'kind' => 'affirmative', 'translation' => 'Она работает допоздна.'],
            ['text' => "Batch {$calls} he **doesn't drive**.", 'kind' => 'negative', 'translation' => 'Он не водит.'],
            ['text' => "Batch {$calls} **does** she **cook**?", 'kind' => 'question', 'translation' => 'Она готовит?'],
        ]];
    });
    app()->instance(AiJsonClient::class, $client);
}

test('examples list puts examples from contents first and returns the marking', function () {
    $rule = makeRuleForExamplesApi();
    $content = Content::factory()->create();
    $rule->examples()->create(['language' => 'en', 'example' => 'Admin one.', 'sort_order' => 10]);
    $rule->examples()->create([
        'language' => 'en', 'example' => 'She works late.', 'origin' => GrammarRuleExample::ORIGIN_AI,
        'kind' => 'affirmative', 'target_spans' => [[4, 9]], 'translation' => 'т', 'sort_order' => 20,
    ]);
    $rule->examples()->create(['language' => 'en', 'example' => 'From the video.', 'content_id' => $content->id, 'sort_order' => 30]);

    $response = $this->getJson("/api/grammar-rules/{$rule->id}/examples");

    $response->assertOk()
        ->assertJsonPath('examples.0.example', 'From the video.')
        ->assertJsonPath('examples.0.origin', 'content')
        ->assertJsonPath('examples.0.from_content', true)
        ->assertJsonPath('examples.1.example', 'Admin one.')
        ->assertJsonPath('examples.2.example', 'She works late.')
        ->assertJsonPath('examples.2.origin', 'ai')
        ->assertJsonPath('examples.2.kind', 'affirmative')
        ->assertJsonPath('examples.2.target_spans', [[4, 9]])
        ->assertJsonPath('generation.status', 'idle');
});

test('examples of an unpublished rule are not available', function () {
    $rule = makeRuleForExamplesApi(GrammarRule::STATUS_DRAFT);

    $this->getJson("/api/grammar-rules/{$rule->id}/examples")->assertNotFound();
});

test('more examples queues one AI batch in the learner translation language and adds new examples', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makeRuleForExamplesApi();
    fakeExamplesAi();

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")
        ->assertStatus(202)
        ->assertJsonPath('status', 'queued');

    $generation = GrammarRuleExampleGeneration::query()->sole();
    expect($generation->status)->toBe(GrammarRuleExampleGeneration::STATUS_DONE)
        ->and($generation->user_id)->toBe($user->id)
        ->and($generation->translation_language)->toBe('ru')
        ->and($generation->created_count)->toBe(3)
        ->and($rule->examples()->where('origin', 'ai')->count())->toBe(3)
        ->and($rule->examples()->first()->translation_language)->toBe('ru');

    $this->getJson("/api/grammar-rules/{$rule->id}/examples")
        ->assertJsonCount(3, 'examples')
        ->assertJsonPath('generation.status', 'done');
});

test('more examples is rate-limited per learner per rule per day', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makeRuleForExamplesApi();
    config(['ai.examples.daily_batches_per_rule' => 2]);
    fakeExamplesAi(2);

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")->assertStatus(202);
    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")->assertStatus(202);
    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")
        ->assertStatus(429)
        ->assertJsonPath('status', 'limited');

    expect(GrammarRuleExampleGeneration::query()->count())->toBe(2);
});

test('more examples does not queue a second batch while one is active for the rule', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makeRuleForExamplesApi();
    $dispatcher = Mockery::mock(GrammarRuleExampleGenerationDispatcher::class);
    $dispatcher->shouldReceive('dispatch')->once();
    app()->instance(GrammarRuleExampleGenerationDispatcher::class, $dispatcher);

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")->assertStatus(202);
    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")
        ->assertOk()
        ->assertJsonPath('status', 'active');

    $this->getJson("/api/grammar-rules/{$rule->id}/examples")->assertJsonPath('generation.status', 'queued');
});

test('a failed AI batch is reported as failed and keeps existing examples', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makeRuleForExamplesApi();
    $rule->examples()->create(['language' => 'en', 'example' => 'Kept.', 'sort_order' => 10]);
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andThrow(new \App\Exceptions\AiClientException('provider down'));
    app()->instance(AiJsonClient::class, $client);

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")->assertStatus(202);

    $this->getJson("/api/grammar-rules/{$rule->id}/examples")
        ->assertJsonPath('generation.status', 'failed')
        ->assertJsonCount(1, 'examples');
});

test('more examples answers unavailable when AI is off', function () {
    Sanctum::actingAs(User::factory()->create(), [], 'sanctum');
    $rule = makeRuleForExamplesApi();
    config(['ai.enabled' => false]);

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")
        ->assertStatus(503)
        ->assertJsonPath('status', 'unavailable');
});

test('more examples needs a signed-in learner', function () {
    $rule = makeRuleForExamplesApi();

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/generate")->assertUnauthorized();
});

test('a learner can hide a bad example for themselves only', function () {
    $rule = makeRuleForExamplesApi();
    $bad = $rule->examples()->create(['language' => 'en', 'example' => 'Bad one.', 'origin' => 'ai', 'sort_order' => 10]);
    $rule->examples()->create(['language' => 'en', 'example' => 'Good one.', 'sort_order' => 20]);
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/{$bad->id}/hide")->assertNoContent();
    $this->postJson("/api/grammar-rules/{$rule->id}/examples/{$bad->id}/hide")->assertNoContent();

    $this->getJson("/api/grammar-rules/{$rule->id}/examples")
        ->assertJsonCount(1, 'examples')
        ->assertJsonPath('examples.0.example', 'Good one.');

    Sanctum::actingAs(User::factory()->create(), [], 'sanctum');
    $this->getJson("/api/grammar-rules/{$rule->id}/examples")->assertJsonCount(2, 'examples');
    expect($bad->fresh())->not->toBeNull();
});

test('hiding an example of another rule is not found', function () {
    $rule = makeRuleForExamplesApi();
    $other = makeRuleForExamplesApi();
    $example = $other->examples()->create(['language' => 'en', 'example' => 'Elsewhere.', 'sort_order' => 10]);
    Sanctum::actingAs(User::factory()->create(), [], 'sanctum');

    $this->postJson("/api/grammar-rules/{$rule->id}/examples/{$example->id}/hide")->assertNotFound();
});

test('the rule page payload carries the example marking and hides hidden examples', function () {
    $rule = makeRuleForExamplesApi();
    $hidden = $rule->examples()->create(['language' => 'en', 'example' => 'Hidden.', 'sort_order' => 10]);
    $rule->examples()->create([
        'language' => 'en', 'example' => 'She works.', 'kind' => 'affirmative', 'target_spans' => [[4, 9]], 'sort_order' => 20,
    ]);
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $this->postJson("/api/grammar-rules/{$rule->id}/examples/{$hidden->id}/hide")->assertNoContent();

    $this->getJson("/api/grammar-rules/{$rule->id}")
        ->assertOk()
        ->assertJsonCount(1, 'rule.examples')
        ->assertJsonPath('rule.examples.0.target_spans', [[4, 9]])
        ->assertJsonPath('rule.examples.0.kind', 'affirmative');
});
