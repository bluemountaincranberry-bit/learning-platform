<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Student\GetLearningHistoryTool;
use App\Modules\Ai\Application\Agent\Tools\Student\GetUserMistakesTool;
use App\Modules\Ai\Application\Agent\Tools\Student\GetWeakTopicsTool;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSrsCardFor(User $user, Content $content, string $itemKey, ?\Illuminate\Support\Carbon $nextReviewAt = null): SrsCard
{
    return SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => $itemKey,
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => $nextReviewAt ?? now()->addDay(),
    ]);
}

// --- GetUserMistakesTool -----------------------------------------------

test('sideEffect is read_only for GetUserMistakesTool', function () {
    expect(app(GetUserMistakesTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('get user mistakes returns failed reviews only, most recent first', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $card = makeSrsCardFor($user, $content, 'word:fail');
    $okCard = makeSrsCardFor($user, $content, 'word:ok');

    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'reviewed_at' => now()->subMinutes(5)]);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 2, 'reviewed_at' => now()->subMinute()]);
    SrsReview::query()->create(['srs_card_id' => $okCard->id, 'grade' => 5, 'reviewed_at' => now()]);

    $result = app(GetUserMistakesTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['mistake_count'])->toBe(2)
        ->and($result['mistakes'][0]['item'])->toBe('fail')
        ->and($result['mistakes'][0]['grade'])->toBe(2);
});

test('get user mistakes reports no data for a student with no failed reviews', function () {
    $user = User::factory()->create();

    $result = app(GetUserMistakesTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['mistake_count'])->toBe(0)->and($result)->toHaveKey('note');
});

test('get user mistakes only counts the acting user\'s own reviews', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $content = Content::factory()->create();
    $otherCard = makeSrsCardFor($other, $content, 'word:notmine');
    SrsReview::query()->create(['srs_card_id' => $otherCard->id, 'grade' => 1, 'reviewed_at' => now()]);

    $result = app(GetUserMistakesTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['mistake_count'])->toBe(0);
});

// --- GetLearningHistoryTool ---------------------------------------------

test('sideEffect is read_only for GetLearningHistoryTool', function () {
    expect(app(GetLearningHistoryTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('get learning history returns learned words most recently learned first, scoped to the user', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $older = Lexeme::query()->create(['slug' => 'en-old', 'language' => 'en', 'lemma' => 'old', 'normalized_lemma' => 'old', 'status' => 'published', 'level' => 'A1']);
    $newer = Lexeme::query()->create(['slug' => 'en-new', 'language' => 'en', 'lemma' => 'new', 'normalized_lemma' => 'new', 'status' => 'published', 'level' => 'B1']);

    $oldCl = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'old', 'sort_order' => 1, 'lexeme_id' => $older->id]);
    $newCl = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'new', 'sort_order' => 2, 'lexeme_id' => $newer->id]);

    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $oldCl->id, 'lexeme_id' => $older->id, 'learned_at' => now()->subDays(3)]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $newCl->id, 'lexeme_id' => $newer->id, 'learned_at' => now()->subDay()]);

    $result = app(GetLearningHistoryTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(2)
        ->and($result['words'][0]['lemma'])->toBe('new')
        ->and($result['words'][1]['lemma'])->toBe('old');
});

test('get learning history scopes to language when provided', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $en = Lexeme::query()->create(['slug' => 'en-gate2', 'language' => 'en', 'lemma' => 'gate', 'normalized_lemma' => 'gate', 'status' => 'published']);
    $fr = Lexeme::query()->create(['slug' => 'fr-porte2', 'language' => 'fr', 'lemma' => 'porte', 'normalized_lemma' => 'porte', 'status' => 'published']);

    $enCl = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'gate', 'sort_order' => 1, 'lexeme_id' => $en->id]);
    $frCl = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'porte', 'sort_order' => 2, 'lexeme_id' => $fr->id]);

    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $enCl->id, 'lexeme_id' => $en->id, 'learned_at' => now()]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $frCl->id, 'lexeme_id' => $fr->id, 'learned_at' => now()]);

    $result = app(GetLearningHistoryTool::class)->execute(['language' => 'fr'], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(1)
        ->and($result['words'][0]['lemma'])->toBe('porte');
});

// --- GetWeakTopicsTool ---------------------------------------------------

test('sideEffect is read_only for GetWeakTopicsTool', function () {
    expect((new GetWeakTopicsTool)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('get weak topics aggregates failed reviews by linked grammar rule', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $lexeme = Lexeme::query()->create(['slug' => 'en-run3', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => 'published']);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'run', 'sort_order' => 1, 'lexeme_id' => $lexeme->id]);

    $topic = GrammarTopic::query()->create(['slug' => 'present-perfect-'.uniqid(), 'language' => 'en', 'name' => 'Present Perfect', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'present-perfect-rule-'.uniqid(), 'language' => 'en', 'title' => 'Present Perfect', 'status' => 'published']);
    $rule->lexemes()->attach($lexeme->id);

    $card = makeSrsCardFor($user, $content, 'word:run');
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'reviewed_at' => now()]);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 2, 'reviewed_at' => now()]);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 5, 'reviewed_at' => now()]); // passed, not a mistake

    $result = (new GetWeakTopicsTool)->execute([], new AgentToolContext(1, $user->id));

    expect($result['weak_topics'])->toHaveCount(1)
        ->and($result['weak_topics'][0]['title'])->toBe('Present Perfect')
        ->and($result['weak_topics'][0]['mistake_count'])->toBe(2);
});

test('get weak topics reports no data when no failed review links to a grammar rule', function () {
    $user = User::factory()->create();

    $result = (new GetWeakTopicsTool)->execute([], new AgentToolContext(1, $user->id));

    expect($result['weak_topics'])->toBe([])->and($result)->toHaveKey('note');
});

test('get weak topics resolves contentless cards from canonical lexeme rules', function () {
    $user = User::factory()->create();
    $topic = GrammarTopic::query()->create(['slug' => 'personal-topic-'.uniqid(), 'language' => 'en', 'name' => 'Personal Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'personal-rule-'.uniqid(), 'language' => 'en', 'title' => 'Personal Rule', 'status' => 'published']);
    $lexeme = Lexeme::query()->create(['slug' => 'en-retain-'.uniqid(), 'language' => 'en', 'lemma' => 'retain', 'normalized_lemma' => 'retain', 'status' => 'published']);
    $lexeme->rules()->attach($rule->id);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_id' => null,
        'item_key' => null,
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.1,
        'next_review_at' => now(),
    ]);
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'reviewed_at' => now()]);

    $weakTopic = (new GetWeakTopicsTool)->execute([], new AgentToolContext(1, $user->id))['weak_topics'][0];

    expect($weakTopic['grammar_rule_id'])->toBe($rule->id)->and($weakTopic['mistake_count'])->toBe(1);
});
