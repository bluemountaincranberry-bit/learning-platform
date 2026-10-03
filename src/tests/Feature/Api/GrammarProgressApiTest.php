<?php

use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makePublishedGrammarRuleForProgress(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-progress-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-progress-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'summary' => 'Used for past actions with present relevance.',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

test('start-learning adds the rule to the users list', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $response = $this->postJson("/api/grammar-rules/{$rule->id}/start-learning");

    $response->assertOk()->assertJson(['ok' => true]);
    expect(UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first()->status)
        ->toBe(UserGrammarRule::STATUS_LEARNING);
});

test('start-learning is idempotent', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertOk();
    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertOk();

    expect(UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->count())->toBe(1);
});

test('mark-learned sets status learned and learned_at', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $response = $this->postJson("/api/grammar-rules/{$rule->id}/mark-learned");

    $response->assertOk();
    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress->status)->toBe(UserGrammarRule::STATUS_LEARNED)
        ->and($progress->learned_at)->not->toBeNull();
});

test('mark-learned after start-learning preserves the original started_at', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertOk();
    $startedAt = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first()->started_at;

    Carbon::setTestNow(now()->addHour());
    $this->postJson("/api/grammar-rules/{$rule->id}/mark-learned")->assertOk();
    Carbon::setTestNow();

    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress->started_at->equalTo($startedAt))->toBeTrue();
});

test('unmark-learned removes the rule from the list entirely', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();
    $this->postJson("/api/grammar-rules/{$rule->id}/mark-learned")->assertOk();

    $response = $this->postJson("/api/grammar-rules/{$rule->id}/unmark-learned");

    $response->assertOk();
    expect(UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->exists())->toBeFalse();
});

test('progress endpoints require authentication', function () {
    $rule = makePublishedGrammarRuleForProgress();

    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertUnauthorized();
});

test('progress endpoints 404 for a non-published rule', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();
    $rule->update(['status' => GrammarRule::STATUS_DRAFT]);

    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertNotFound();
});

test('grammar rule show includes in_my_list and learned flags for an authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();
    $this->postJson("/api/grammar-rules/{$rule->id}/start-learning")->assertOk();

    $response = $this->getJson("/api/grammar-rules/{$rule->id}");

    $response->assertOk()
        ->assertJsonPath('rule.in_my_list', true)
        ->assertJsonPath('rule.learned', false);
});

test('grammar rule show omits in_my_list and learned flags for a guest', function () {
    $rule = makePublishedGrammarRuleForProgress();

    $response = $this->getJson("/api/grammar-rules/{$rule->id}");

    $response->assertOk()
        ->assertJsonMissingPath('rule.in_my_list')
        ->assertJsonMissingPath('rule.learned');
});

test('me/grammar-rules lists the users list, filterable by status', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $learning = makePublishedGrammarRuleForProgress();
    $learned = makePublishedGrammarRuleForProgress();
    $this->postJson("/api/grammar-rules/{$learning->id}/start-learning")->assertOk();
    $this->postJson("/api/grammar-rules/{$learned->id}/mark-learned")->assertOk();

    $all = $this->getJson('/api/me/grammar-rules')->assertOk()->json('data');
    expect(collect($all)->pluck('grammar_rule_id'))->toContain($learning->id, $learned->id);

    $learnedOnly = $this->getJson('/api/me/grammar-rules?status=learned')->assertOk()->json('data');
    expect(collect($learnedOnly)->pluck('grammar_rule_id'))->toContain($learned->id)
        ->and(collect($learnedOnly)->pluck('grammar_rule_id'))->not->toContain($learning->id);
});

test('me/grammar-rules requires authentication', function () {
    $this->getJson('/api/me/grammar-rules')->assertUnauthorized();
});

test('confidence sets confidence_manual without touching confidence_calculated', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $response = $this->postJson("/api/grammar-rules/{$rule->id}/confidence", ['confidence' => 65]);

    $response->assertOk();
    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress->confidence_manual)->toBe(65.0)
        ->and($progress->confidence_calculated)->toBeNull()
        ->and($progress->status)->toBe(UserGrammarRule::STATUS_LEARNING);
});

test('confidence accepts null to clear a previous rating', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();
    $this->postJson("/api/grammar-rules/{$rule->id}/confidence", ['confidence' => 40])->assertOk();

    $this->postJson("/api/grammar-rules/{$rule->id}/confidence", ['confidence' => null])->assertOk();

    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress->confidence_manual)->toBeNull();
});

test('confidence rejects values outside 0-100', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();

    $this->postJson("/api/grammar-rules/{$rule->id}/confidence", ['confidence' => 150])->assertStatus(422);
});

test('grammar rule show includes confidence fields for an authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, [], 'sanctum');
    $rule = makePublishedGrammarRuleForProgress();
    $this->postJson("/api/grammar-rules/{$rule->id}/confidence", ['confidence' => 30])->assertOk();

    $response = $this->getJson("/api/grammar-rules/{$rule->id}");

    $response->assertOk()
        ->assertJsonPath('rule.confidence_manual', 30)
        ->assertJsonPath('rule.confidence_calculated', null);
});
