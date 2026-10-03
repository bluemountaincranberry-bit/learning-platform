<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Data\AgentToolDefinition;
use App\Modules\Ai\Application\Agent\Tools\Student\GetReviewScheduleTool;
use App\Modules\Ai\Application\Agent\Tools\Student\GetVocabularySizeTool;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function learnWordFor(User $user, string $language, ?string $level, string $lemma): void
{
    $content = Content::factory()->create();
    $lexeme = Lexeme::query()->create([
        'slug' => "{$language}-{$lemma}-".uniqid(),
        'language' => $language,
        'lemma' => $lemma,
        'normalized_lemma' => $lemma,
        'status' => 'published',
        'level' => $level,
    ]);
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => $lemma,
        'sort_order' => 1,
        'lexeme_id' => $lexeme->id,
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $contentLexeme->id,
        'lexeme_id' => $lexeme->id,
        'learned_at' => now(),
    ]);
}

// --- GetVocabularySizeTool ------------------------------------------------

test('sideEffect is read_only for GetVocabularySizeTool', function () {
    expect(app(GetVocabularySizeTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('get vocabulary size counts learned words with a level breakdown', function () {
    $user = User::factory()->create();
    learnWordFor($user, 'en', 'A1', 'cat');
    learnWordFor($user, 'en', 'A1', 'dog');
    learnWordFor($user, 'en', 'B1', 'ambitious');
    learnWordFor($user, 'en', null, 'unlevelled');

    $result = app(GetVocabularySizeTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['total_learned_word_count'])->toBe(4)
        ->and($result['level_breakdown'])->toBe(['A1' => 2, 'B1' => 1]);
});

test('get vocabulary size scopes to language and to the acting user', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    learnWordFor($user, 'en', 'A2', 'gate');
    learnWordFor($user, 'fr', 'B2', 'porte');
    learnWordFor($other, 'en', 'C1', 'meticulous');

    $result = app(GetVocabularySizeTool::class)->execute(['language' => 'en'], new AgentToolContext(1, $user->id));

    expect($result['total_learned_word_count'])->toBe(1);
});

// --- GetReviewScheduleTool ------------------------------------------------

test('sideEffect is read_only for GetReviewScheduleTool', function () {
    expect(app(GetReviewScheduleTool::class)->definition()->sideEffect)->toBe(AgentToolDefinition::SIDE_EFFECT_READ_ONLY);
});

test('get review schedule reports due count and orders upcoming cards', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'word:due',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5,
        'next_review_at' => now()->subHour(),
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'word:soon',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5,
        'next_review_at' => now()->addHour(),
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'word:later',
        'state' => 'reviewing', 'interval_days' => 5, 'ease_factor' => 2.5,
        'next_review_at' => now()->addDays(3),
    ]);

    $result = app(GetReviewScheduleTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['due_now_count'])->toBe(1)
        ->and($result['upcoming'])->toHaveCount(3)
        ->and($result['upcoming'][0]['item'])->toBe('due')
        ->and($result['upcoming'][0]['is_due'])->toBeTrue()
        ->and($result['upcoming'][2]['item'])->toBe('later')
        ->and($result['upcoming'][2]['is_due'])->toBeFalse();
});

test('get review schedule only counts the acting user\'s own cards', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $content = Content::factory()->create();

    SrsCard::query()->create([
        'user_id' => $other->id, 'content_id' => $content->id, 'item_key' => 'word:notmine',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5,
        'next_review_at' => now()->subHour(),
    ]);

    $result = app(GetReviewScheduleTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['due_now_count'])->toBe(0)->and($result['upcoming'])->toBe([]);
});
