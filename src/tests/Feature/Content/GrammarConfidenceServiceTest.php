<?php

use App\Modules\Content\Application\GrammarConfidenceService;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarExamAttempt;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGrammarRuleForConfidence(): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
}

/**
 * Links a lexeme to the rule (grammar_rule_lexeme) and gives it one SRS
 * review at the given grade, wiring srs_cards.item_key the same way
 * GetWeakTopicsTool/GrammarConfidenceService's join expects it.
 */
function addSrsReviewForRule(User $user, Content $content, GrammarRule $rule, int $grade): void
{
    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => 'en',
        'lemma' => 'have done',
        'normalized_lemma' => 'have done',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $rule->lexemes()->attach($lexeme->id);

    ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'phrase',
        'text' => 'have done',
        'lexeme_id' => $lexeme->id,
    ]);

    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'phrase:have done',
        'state' => 'review',
        'next_review_at' => now(),
    ]);

    SrsReview::query()->create([
        'srs_card_id' => $card->id,
        'grade' => $grade,
        'reviewed_at' => now(),
    ]);
}

test('recalculate returns null and writes nothing when there is no signal', function () {
    $user = User::factory()->create();
    $rule = makeGrammarRuleForConfidence();

    $result = app(GrammarConfidenceService::class)->recalculate($user->id, $rule);

    expect($result)->toBeNull();
    expect(UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->exists())->toBeFalse();
});

test('recalculate uses the average of recent exam attempts when there is no SRS signal', function () {
    $user = User::factory()->create();
    $rule = makeGrammarRuleForConfidence();

    GrammarExamAttempt::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'type' => GrammarExamAttempt::TYPE_PRE,
        'total_cards' => 2,
        'correct_count' => 2,
        'score_pct' => 100,
        'items' => [],
        'completed_at' => now(),
    ]);
    GrammarExamAttempt::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'type' => GrammarExamAttempt::TYPE_PRE,
        'total_cards' => 2,
        'correct_count' => 1,
        'score_pct' => 50,
        'items' => [],
        'completed_at' => now(),
    ]);

    $result = app(GrammarConfidenceService::class)->recalculate($user->id, $rule);

    expect($result)->toBe(75.0);

    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress)->not->toBeNull()
        ->and($progress->confidence_calculated)->toBe(75.0)
        ->and($progress->status)->toBe(UserGrammarRule::STATUS_LEARNING);
});

test('recalculate uses the SRS pass rate when there are no exam attempts', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $rule = makeGrammarRuleForConfidence();

    addSrsReviewForRule($user, $content, $rule, grade: 4); // pass (> 2)

    $result = app(GrammarConfidenceService::class)->recalculate($user->id, $rule);

    expect($result)->toBe(100.0);
});

test('recalculate blends exam and SRS signals 70/30 when both exist', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $rule = makeGrammarRuleForConfidence();

    GrammarExamAttempt::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'type' => GrammarExamAttempt::TYPE_PRE,
        'total_cards' => 1,
        'correct_count' => 1,
        'score_pct' => 100,
        'items' => [],
        'completed_at' => now(),
    ]);
    addSrsReviewForRule($user, $content, $rule, grade: 1); // fail (<= 2) -> SRS score 0

    $result = app(GrammarConfidenceService::class)->recalculate($user->id, $rule);

    // 100 * 0.7 + 0 * 0.3 = 70
    expect($result)->toBe(70.0);
});

test('an existing UserGrammarRule keeps its status/started_at when confidence is recalculated', function () {
    $user = User::factory()->create();
    $rule = makeGrammarRuleForConfidence();
    $startedAt = now()->subDays(10);
    UserGrammarRule::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'status' => UserGrammarRule::STATUS_LEARNED,
        'started_at' => $startedAt,
        'learned_at' => now(),
    ]);

    GrammarExamAttempt::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'type' => GrammarExamAttempt::TYPE_POST,
        'total_cards' => 1,
        'correct_count' => 1,
        'score_pct' => 100,
        'items' => [],
        'completed_at' => now(),
    ]);

    app(GrammarConfidenceService::class)->recalculate($user->id, $rule);

    $progress = UserGrammarRule::query()->where('user_id', $user->id)->where('grammar_rule_id', $rule->id)->first();
    expect($progress->status)->toBe(UserGrammarRule::STATUS_LEARNED)
        ->and($progress->started_at->timestamp)->toBe($startedAt->timestamp);
});
