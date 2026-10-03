<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    config(['ai.enabled' => true]);
});

/**
 * @return array{0: Content, 1: GrammarRule, 2: GrammarRule}
 */
function makeContentWithTwoGrammarRules(): array
{
    $content = Content::factory()->create(['language' => 'en']);
    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    $ruleA = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'rule-a-'.uniqid(), 'language' => 'en',
        'title' => 'Present Perfect', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $ruleB = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'rule-b-'.uniqid(), 'language' => 'en',
        'title' => 'Passive Voice', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $content->grammarRules()->attach([$ruleA->id, $ruleB->id]);

    return [$content, $ruleA, $ruleB];
}

test('start rejects grammar rule ids not linked to this content', function () {
    $user = User::factory()->create();
    [$content] = makeContentWithTwoGrammarRules();
    $otherRule = GrammarRule::query()->create([
        'topic_id' => GrammarTopic::query()->create(['slug' => 'topic-x-'.uniqid(), 'language' => 'en', 'name' => 'X', 'status' => 'active'])->id,
        'slug' => 'rule-x-'.uniqid(), 'language' => 'en', 'title' => 'Unrelated', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    test()->actingAs($user);

    $response = test()->postJson("/api/content/{$content->id}/grammar-warmup/start", [
        'grammar_rule_ids' => [$otherRule->id],
    ]);

    $response->assertStatus(422);
});

test('start generates cards tagged with the selected grammar rule ids', function () {
    $user = User::factory()->create();
    [$content, $ruleA, $ruleB] = makeContentWithTwoGrammarRules();
    test()->actingAs($user);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturn([
        'sentences' => [['text' => 'She has finished.', 'translation' => 'Она закончила.', 'uses' => ['Present Perfect']]],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $response = test()->postJson("/api/content/{$content->id}/grammar-warmup/start", [
        'grammar_rule_ids' => [$ruleA->id, $ruleB->id],
    ]);

    $response->assertOk();
    $cards = $response->json('cards');
    expect($cards)->not->toBeEmpty();
    $ruleIds = collect($cards)->pluck('grammar_rule_id')->unique()->sort()->values()->all();
    expect($ruleIds)->toBe(collect([$ruleA->id, $ruleB->id])->sort()->values()->all());
});

test('complete writes one attempt per rule and updates confidence_calculated', function () {
    $user = User::factory()->create();
    [$content, $ruleA, $ruleB] = makeContentWithTwoGrammarRules();
    test()->actingAs($user);

    $response = test()->postJson("/api/content/{$content->id}/grammar-warmup/complete", [
        'type' => 'pre',
        'results' => [
            ['grammar_rule_id' => $ruleA->id, 'prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true],
            ['grammar_rule_id' => $ruleA->id, 'prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => false],
            ['grammar_rule_id' => $ruleB->id, 'prompt_sentence' => 'C', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'c', 'correct' => true],
        ],
    ]);

    $response->assertCreated();
    $attempts = collect($response->json('attempts'))->keyBy('grammar_rule_id');

    expect($attempts[$ruleA->id]['attempt']['score_pct'])->toBe(50)
        ->and($attempts[$ruleA->id]['confidence_calculated'])->toBe(50)
        ->and($attempts[$ruleB->id]['attempt']['score_pct'])->toBe(100)
        ->and($attempts[$ruleB->id]['confidence_calculated'])->toBe(100);

    $progressA = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $ruleA->id)->first();
    expect($progressA->confidence_calculated)->toBe(50.0);
});

test('complete ignores results for grammar rules not linked to the content', function () {
    $user = User::factory()->create();
    [$content, $ruleA] = makeContentWithTwoGrammarRules();
    $otherRule = GrammarRule::query()->create([
        'topic_id' => GrammarTopic::query()->create(['slug' => 'topic-y-'.uniqid(), 'language' => 'en', 'name' => 'Y', 'status' => 'active'])->id,
        'slug' => 'rule-y-'.uniqid(), 'language' => 'en', 'title' => 'Unrelated', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    test()->actingAs($user);

    $response = test()->postJson("/api/content/{$content->id}/grammar-warmup/complete", [
        'type' => 'pre',
        'results' => [
            ['grammar_rule_id' => $ruleA->id, 'prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true],
            ['grammar_rule_id' => $otherRule->id, 'prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => true],
        ],
    ]);

    $response->assertCreated();
    $attempts = collect($response->json('attempts'));
    expect($attempts)->toHaveCount(1)
        ->and($attempts->first()['grammar_rule_id'])->toBe($ruleA->id);
});
