<?php

use App\Exceptions\AiClientException;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Ai\Application\SentencePracticeService;
use App\Contracts\Ai\AiJsonClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRecentlyLearnedWord(User $user, string $language = 'en', string $text = 'apple'): void
{
    $content = Content::factory()->create(['language' => $language, 'status' => 'ready']);
    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => $language,
        'lemma' => $text,
        'normalized_lemma' => strtolower($text),
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $contentLexeme = ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => $text,
        'lexeme_id' => $lexeme->id,
    ]);
    UserLexemeSource::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'source_kind' => 'content',
        'content_lexeme_id' => $contentLexeme->id,
        'source_text' => $text,
        'display_label_snapshot' => $text,
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_id' => $content->id,
        'item_key' => "word:{$text}",
        'state' => 'new',
        'interval_days' => 1,
        'ease_factor' => 2.50,
        'next_review_at' => now(),
    ]);
}

test('generateBatch returns an empty result with a note when the user has no recent study data', function () {
    $user = User::factory()->create();

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    app()->instance(AiJsonClient::class, $client);

    $result = app(SentencePracticeService::class)->generateBatch($user, SentencePracticeService::DIRECTION_TO_TARGET);

    expect($result['cards'])->toBe([])
        ->and($result['note'])->not->toBeNull();
});

test('generateBatch builds cards from words in the learning queue', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    makeRecentlyLearnedWord($user, 'en', 'apple');

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'sentences' => [
            ['text' => 'I bought an apple.', 'translation' => 'Я купил яблоко.', 'uses' => ['apple']],
            ['text' => '', 'translation' => '', 'uses' => []], // malformed: empty text, must be skipped
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $result = app(SentencePracticeService::class)->generateBatch($user, SentencePracticeService::DIRECTION_TO_NATIVE);

    expect($result['cards'])->toHaveCount(1)
        ->and($result['cards'][0]['prompt_sentence'])->toBe('I bought an apple.')
        ->and($result['cards'][0]['prompt_language'])->toBe('en')
        ->and($result['cards'][0]['answer_language'])->toBe('ru');
});

test('generateBatch throws when the AI returns no usable sentences', function () {
    $user = User::factory()->create();
    makeRecentlyLearnedWord($user);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['sentences' => []]);
    app()->instance(AiJsonClient::class, $client);

    expect(fn () => app(SentencePracticeService::class)->generateBatch($user, SentencePracticeService::DIRECTION_TO_TARGET))
        ->toThrow(AiClientException::class);
});

test('recentContext keeps grammar context when no learning words exist', function () {
    $user = User::factory()->create();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    UserGrammarRule::query()->create([
        'user_id' => $user->id,
        'grammar_rule_id' => $rule->id,
        'status' => UserGrammarRule::STATUS_LEARNING,
        'started_at' => now(),
    ]);

    $context = app(SentencePracticeService::class)->recentContext($user);

    expect($context['target_language'])->toBe('en')
        ->and($context['grammar_topics'])->toBe(['Present Perfect']);
});

test('checkAnswer parses a valid grading response', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn([
        'correct' => true,
        'feedback' => 'Well done.',
        'model_answer' => 'Я купил яблоко.',
    ]);
    app()->instance(AiJsonClient::class, $client);

    $result = app(SentencePracticeService::class)->checkAnswer('I bought an apple.', 'en', 'ru', 'Я купил яблоко.');

    expect($result['correct'])->toBeTrue()
        ->and($result['feedback'])->toBe('Well done.');
});

test('checkAnswer throws when the AI response is missing a usable correct flag', function () {
    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->once()->andReturn(['feedback' => 'oops']);
    app()->instance(AiJsonClient::class, $client);

    expect(fn () => app(SentencePracticeService::class)->checkAnswer('S', 'en', 'ru', 'A'))
        ->toThrow(AiClientException::class);
});

function makeContentForExamPractice(string $language = 'en'): Content
{
    $content = Content::factory()->create(['language' => $language, 'status' => 'ready']);
    $lexeme = Lexeme::query()->create([
        'slug' => 'lex-'.uniqid(),
        'language' => $language,
        'lemma' => 'jump',
        'normalized_lemma' => 'jump',
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => 'jump',
        'lexeme_id' => $lexeme->id,
    ]);
    $topic = GrammarTopic::query()->create(['slug' => 'topic-'.uniqid(), 'language' => $language, 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-'.uniqid(),
        'language' => $language,
        'title' => 'Simple Past',
        'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $content->grammarRules()->attach($rule->id);

    return $content;
}

test('contentContext pulls only words from this content in the learning queue', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    $content = makeContentForExamPractice();
    $contentLexeme = $content->lexemes()->firstOrFail();
    UserLexemeSource::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $contentLexeme->lexeme_id,
        'source_kind' => 'content',
        'content_lexeme_id' => $contentLexeme->id,
        'source_text' => $contentLexeme->text,
        'display_label_snapshot' => $contentLexeme->text,
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $contentLexeme->lexeme_id,
        'content_id' => $content->id,
        'item_key' => "word:{$contentLexeme->text}",
        'state' => 'new',
        'interval_days' => 1,
        'ease_factor' => 2.50,
        'next_review_at' => now(),
    ]);

    $context = app(SentencePracticeService::class)->contentContext($user, $content);

    expect($context['target_language'])->toBe('en')
        ->and($context['native_language'])->toBe('ru')
        ->and($context['words'])->toBe(['jump'])
        ->and($context['grammar_topics'])->toBe(['Simple Past']);
});

test('generateForContent returns a note instead of calling the AI when the content has nothing to draw on', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldNotReceive('completeJson');
    app()->instance(AiJsonClient::class, $client);

    $result = app(SentencePracticeService::class)->generateForContent($user, $content, SentencePracticeService::DIRECTION_TO_TARGET);

    expect($result['cards'])->toBe([])
        ->and($result['note'])->not->toBeNull();
});

test('generateExam splits the requested count across both directions and shuffles them', function () {
    $user = User::factory()->create();
    $content = makeContentForExamPractice();
    $contentLexeme = $content->lexemes()->firstOrFail();
    UserLexemeSource::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $contentLexeme->lexeme_id,
        'source_kind' => 'content',
        'content_lexeme_id' => $contentLexeme->id,
        'source_text' => $contentLexeme->text,
        'display_label_snapshot' => $contentLexeme->text,
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $contentLexeme->lexeme_id,
        'content_id' => $content->id,
        'item_key' => "word:{$contentLexeme->text}",
        'state' => 'new',
        'interval_days' => 1,
        'ease_factor' => 2.50,
        'next_review_at' => now(),
    ]);

    $client = Mockery::mock(AiJsonClient::class);
    $client->shouldReceive('completeJson')->twice()->andReturn([
        'sentences' => [
            ['text' => 'Sentence one.', 'translation' => 'Предложение один.', 'uses' => ['jump']],
            ['text' => 'Sentence two.', 'translation' => 'Предложение два.', 'uses' => ['jump']],
        ],
    ]);
    app()->instance(AiJsonClient::class, $client);

    $cards = app(SentencePracticeService::class)->generateExam($user, $content, 4);

    expect($cards)->toHaveCount(4);
    $directions = collect($cards)->map(fn ($c) => $c['prompt_language'] === 'en' ? 'to_native' : 'to_target')->unique()->values()->all();
    expect($directions)->toContain('to_native')->toContain('to_target');
});

test('generateExam throws when the content has nothing to build an exam from', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);

    expect(fn () => app(SentencePracticeService::class)->generateExam($user, $content, 4))
        ->toThrow(AiClientException::class);
});
