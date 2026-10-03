<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Application\Data\StatsLearner;
use App\Modules\Learning\Application\ProgressStatsService;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeFailedReview(User $user, Content $content, string $itemKey, int $grade = 1): SrsCard
{
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => $itemKey,
    ]);

    SrsReview::query()->create([
        'srs_card_id' => $card->id,
        'grade' => $grade,
        'reviewed_at' => now(),
    ]);

    return $card;
}

test('getWeakWords surfaces failed reviews, worst first, with the un-prefixed item text', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $card = makeFailedReview($user, $content, 'word:apple');
    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 0, 'reviewed_at' => now()]); // second miss on the same word
    makeFailedReview($user, $content, 'phrase:give up');
    makeFailedReview($user, $content, 'word:banana', grade: 3); // passing grade, must not count

    $weakWords = app(ProgressStatsService::class)->getWeakWords(new StatsLearner($user->id, $user->timezone, $user->daily_goal));

    expect($weakWords)->toHaveCount(2)
        ->and($weakWords[0]['lexeme'])->toBe('apple')
        ->and($weakWords[0]['hint'])->toBe('2 missed reviews')
        ->and($weakWords[0]['content_id'])->toBe($content->id)
        ->and(collect($weakWords)->pluck('lexeme'))->not->toContain('banana');
});

test('getWeakGrammarTopics aggregates failed reviews up to the linked grammar rule', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);

    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => 'en',
        'lemma' => 'have lived',
        'normalized_lemma' => 'have lived',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $lexeme->rules()->attach($rule->id);

    ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'phrase',
        'text' => 'have lived',
        'lexeme_id' => $lexeme->id,
    ]);

    makeFailedReview($user, $content, 'phrase:have lived');

    $topics = app(ProgressStatsService::class)->getWeakGrammarTopics(new StatsLearner($user->id, $user->timezone, $user->daily_goal));

    expect($topics)->toHaveCount(1)
        ->and($topics[0]['title'])->toBe('Present Perfect')
        ->and($topics[0]['mistake_count'])->toBe(1);
});
