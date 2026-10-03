<?php

use App\Modules\Content\Actions\RecordContentExamAttempt;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Queries\ContentReadinessQuery;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{0: Content, 1: Lexeme, 2: ContentLexeme, 3: GrammarRule}
 */
function makeContentWithWordAndGrammar(): array
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

test('stepsStatus is incomplete when neither words nor grammar are learned', function () {
    $user = User::factory()->create();
    [$content] = makeContentWithWordAndGrammar();

    $status = app(ContentReadinessQuery::class)->stepsStatus($user->id, $content);

    expect($status['words']['complete'])->toBeFalse()
        ->and($status['grammar']['complete'])->toBeFalse()
        ->and($status['exam_unlocked'])->toBeFalse();
});

test('stepsStatus unlocks the exam once every word and grammar rule is learned', function () {
    $user = User::factory()->create();
    [$content, $lexeme, $contentLexeme, $rule] = makeContentWithWordAndGrammar();

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

    $status = app(ContentReadinessQuery::class)->stepsStatus($user->id, $content);

    expect($status['words']['complete'])->toBeTrue()
        ->and($status['grammar']['complete'])->toBeTrue()
        ->and($status['exam_unlocked'])->toBeTrue();
});

test('content with no linked grammar rules treats the grammar step as trivially complete', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => 'en',
        'lemma' => 'jump',
        'normalized_lemma' => 'jump',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $contentLexeme = ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => 'jump',
        'lexeme_id' => $lexeme->id,
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_lexeme_id' => $contentLexeme->id,
        'learned_at' => now(),
    ]);

    $status = app(ContentReadinessQuery::class)->stepsStatus($user->id, $content);

    expect($status['grammar']['total'])->toBe(0)
        ->and($status['grammar']['complete'])->toBeTrue()
        ->and($status['exam_unlocked'])->toBeTrue();
});

test('recordAttempt computes score, snapshots the threshold, and marks passed correctly', function () {
    config(['ai.exam.pass_threshold_pct' => 75]);
    $user = User::factory()->create();
    $content = Content::factory()->create();

    $results = [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true, 'model_answer' => 'A'],
        ['prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => true, 'model_answer' => 'B'],
        ['prompt_sentence' => 'C', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'c', 'correct' => true, 'model_answer' => 'C'],
        ['prompt_sentence' => 'D', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'd', 'correct' => false, 'model_answer' => 'D'],
    ];

    $attempt = app(RecordContentExamAttempt::class)->execute($user->id, $content, $results);

    expect($attempt->total_cards)->toBe(4)
        ->and($attempt->correct_count)->toBe(3)
        ->and($attempt->score_pct)->toBe(75.0)
        ->and($attempt->passed)->toBeTrue()
        ->and($attempt->pass_threshold_pct)->toBe(75.0)
        ->and($attempt->items)->toHaveCount(4);
});

test('recordAttempt marks failed when below threshold, and isReady/latestAttempt reflect it', function () {
    config(['ai.exam.pass_threshold_pct' => 80]);
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $query = app(ContentReadinessQuery::class);
    $recordAttempt = app(RecordContentExamAttempt::class);

    $recordAttempt->execute($user->id, $content, [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => false, 'model_answer' => 'A'],
        ['prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => true, 'model_answer' => 'B'],
    ]);

    expect($query->isReady($user->id, $content))->toBeFalse();

    $passedAttempt = $recordAttempt->execute($user->id, $content, [
        ['prompt_sentence' => 'A', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'a', 'correct' => true, 'model_answer' => 'A'],
        ['prompt_sentence' => 'B', 'prompt_language' => 'ru', 'answer_language' => 'en', 'answer' => 'b', 'correct' => true, 'model_answer' => 'B'],
    ]);

    expect($query->isReady($user->id, $content))->toBeTrue()
        ->and($query->latestAttempt($user->id, $content)->id)->toBe($passedAttempt->id);
});
