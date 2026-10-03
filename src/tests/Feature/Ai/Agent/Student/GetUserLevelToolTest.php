<?php

use App\Modules\Ai\Application\Agent\Data\AgentToolContext;
use App\Modules\Ai\Application\Agent\Tools\Student\GetUserLevelTool;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function learnLexemeForUser(User $user, string $language, string $level, string $lemma): void
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

test('reports no data yet when the student has not learned any leveled vocabulary', function () {
    $user = User::factory()->create();

    $result = app(GetUserLevelTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(0)
        ->and($result['estimated_level'])->toBeNull()
        ->and($result)->toHaveKey('note');
});

test('estimates the level with the most learned words', function () {
    $user = User::factory()->create();
    learnLexemeForUser($user, 'en', 'A1', 'cat');
    learnLexemeForUser($user, 'en', 'A1', 'dog');
    learnLexemeForUser($user, 'en', 'A1', 'run');
    learnLexemeForUser($user, 'en', 'B1', 'ambitious');

    $result = app(GetUserLevelTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(4)
        ->and($result['estimated_level'])->toBe('A1')
        ->and($result['level_breakdown'])->toBe(['A1' => 3, 'B1' => 1]);
});

test('only counts the acting user\'s own learned words, not another user\'s', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    learnLexemeForUser($otherUser, 'en', 'C1', 'meticulous');

    $result = app(GetUserLevelTool::class)->execute([], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(0);
});

test('scopes to the given language when provided', function () {
    $user = User::factory()->create();
    learnLexemeForUser($user, 'en', 'A2', 'gate');
    learnLexemeForUser($user, 'fr', 'B2', 'porte');

    $result = app(GetUserLevelTool::class)->execute(['language' => 'en'], new AgentToolContext(1, $user->id));

    expect($result['learned_word_count'])->toBe(1)
        ->and($result['estimated_level'])->toBe('A2');
});
