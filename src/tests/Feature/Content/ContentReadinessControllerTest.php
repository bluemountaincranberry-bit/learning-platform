<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    config(['ai.enabled' => true]);
});

/**
 * @return array{0: Content, 1: Lexeme, 2: ContentLexeme, 3: GrammarRule}
 */
function makeReadinessContent(): array
{
    $content = Content::factory()->create(['language' => 'en']);

    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => 'en',
        'lemma' => 'run',
        'normalized_lemma' => 'run',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $contentLexeme = ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => 'run',
        'lexeme_id' => $lexeme->id,
    ]);

    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Simple',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $content->grammarRules()->attach($rule->id);

    return [$content, $lexeme, $contentLexeme, $rule];
}

function completeReadinessSteps(User $user, Content $content, Lexeme $lexeme, ContentLexeme $contentLexeme, GrammarRule $rule): void
{
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_lexeme_id' => $contentLexeme->id,
        'learned_at' => now(),
    ]);
    UserGrammarRule::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'status' => UserGrammarRule::STATUS_LEARNED,
        'started_at' => now(),
        'learned_at' => now(),
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:run',
        'state' => 'reviewing',
        'interval_days' => 1,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);
}

test('GET readiness reports step status for the authenticated learner', function () {
    $user = User::factory()->create();
    [$content] = makeReadinessContent();
    test()->actingAs($user);

    $response = test()->getJson("/api/content/{$content->id}/readiness");

    $response->assertOk()
        ->assertJsonPath('steps.exam_unlocked', false)
        ->assertJsonPath('ready', false);
});

test('exam/start returns 409 when steps are not complete', function () {
    $user = User::factory()->create();
    [$content] = makeReadinessContent();
    test()->actingAs($user);

    test()->postJson("/api/content/{$content->id}/readiness/exam/start")->assertStatus(409);
});

test('exam/start generates cards once steps are complete', function () {
    $user = User::factory()->create();
    [$content, $lexeme, $contentLexeme, $rule] = makeReadinessContent();
    completeReadinessSteps($user, $content, $lexeme, $contentLexeme, $rule);
    test()->actingAs($user);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->andReturn([
        'sentences' => [['text' => 'He runs every day.', 'translation' => 'Он бегает каждый день.', 'uses' => ['run']]],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $response = test()->postJson("/api/content/{$content->id}/readiness/exam/start");

    $response->assertOk();
    expect($response->json('cards'))->not->toBeEmpty();
});

test('exam/complete returns 409 when steps are not complete', function () {
    $user = User::factory()->create();
    [$content] = makeReadinessContent();
    test()->actingAs($user);

    test()->postJson("/api/content/{$content->id}/readiness/exam/complete", ['results' => [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true],
    ]])->assertStatus(409);
});

test('exam/complete persists an attempt and flips readiness once passed', function () {
    config(['ai.exam.pass_threshold_pct' => 50]);
    $user = User::factory()->create();
    [$content, $lexeme, $contentLexeme, $rule] = makeReadinessContent();
    completeReadinessSteps($user, $content, $lexeme, $contentLexeme, $rule);
    test()->actingAs($user);

    $response = test()->postJson("/api/content/{$content->id}/readiness/exam/complete", ['results' => [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true],
        ['prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => false],
    ]]);

    $response->assertCreated()
        ->assertJsonPath('passed', true)
        ->assertJsonPath('score_pct', 50);

    $readiness = test()->getJson("/api/content/{$content->id}/readiness");
    $readiness->assertJsonPath('ready', true);
});
