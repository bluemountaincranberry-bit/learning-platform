<?php

use App\Modules\Ai\Application\Data\RecommendationLearner;
use App\Modules\Ai\Application\RecommendationService;
use App\Modules\Ai\Domain\Models\CanonicalLexemeEmbedding;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getRecommendedContents returns ready contents with unlearned lexemes', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'hello',
    ]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'hello', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'world', 'sort_order' => 2]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedContents(RecommendationLearner::fromUser($user), 5);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($result->count())->toBeGreaterThan(0)
        ->and($result->first())->toBeInstanceOf(Content::class)
        ->and($result->first()->status)->toBe('ready');
});

test('getRecommendedContents excludes contents where user learned all lexemes', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Full',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'a', 'sort_order' => 1]);
    $lex2 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'b', 'sort_order' => 2]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lex1->id, 'learned_at' => now()]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lex2->id, 'learned_at' => now()]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedContents(RecommendationLearner::fromUser($user), 5);

    $ids = $result->pluck('id')->toArray();
    expect($ids)->not->toContain($content->id);
});

test('getRecommendedLexemes returns only not-learned lexemes from ready content', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'hello', 'sort_order' => 1]);
    $lex2 = $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'world', 'sort_order' => 2]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $lex1->id, 'learned_at' => now()]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedLexemes(RecommendationLearner::fromUser($user), 10);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    $lexemeIds = $result->pluck('id')->toArray();
    expect($lexemeIds)->not->toContain($lex1->id)
        ->and($lexemeIds)->toContain($lex2->id);
});

test('getRecommendedLexemes respects limit', function () {
    $user = User::factory()->create(['ui_language' => 'en']);
    $content = Content::query()->create([
        'type' => 'song',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    foreach (range(1, 15) as $i) {
        $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => "word{$i}", 'sort_order' => $i]);
    }

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedLexemes(RecommendationLearner::fromUser($user), 5);

    expect($result->count())->toBeLessThanOrEqual(5);
});

// --- Task 4.14: ranking by similarity to learned words + review staleness ---

function canonicalLexemeWithEmbedding(string $lemma, array $embedding): Lexeme
{
    $lexeme = Lexeme::query()->create([
        'slug' => "en-{$lemma}-".uniqid(),
        'language' => 'en',
        'lemma' => $lemma,
        'normalized_lemma' => $lemma,
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);

    CanonicalLexemeEmbedding::query()->create([
        'lexeme_id' => $lexeme->id,
        'embedding' => $embedding,
        'model_version' => 'text-embedding-3-small',
    ]);

    return $lexeme;
}

test('getRecommendedLexemes ranks the word most similar to what the user already knows first', function () {
    $user = User::factory()->create(['ui_language' => 'en']);

    // The user already learned a word embedded at [1, 0].
    $learnedLexeme = canonicalLexemeWithEmbedding('run', [1.0, 0.0]);
    $learnedContent = Content::query()->create(['type' => 'song', 'title' => 'Learned', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $learnedContentLexeme = $learnedContent->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'run', 'sort_order' => 1, 'lexeme_id' => $learnedLexeme->id]);
    UserLexemeProgress::query()->create(['user_id' => $user->id, 'content_lexeme_id' => $learnedContentLexeme->id, 'lexeme_id' => $learnedLexeme->id, 'learned_at' => now()]);

    // Candidate 1: nearly identical direction to the learned centroid — should rank first.
    $similarLexeme = canonicalLexemeWithEmbedding('jog', [0.99, 0.01]);
    // Candidate 2: orthogonal — should rank behind it.
    $dissimilarLexeme = canonicalLexemeWithEmbedding('bookshelf', [0.0, 1.0]);

    $content = Content::query()->create(['type' => 'song', 'title' => 'Candidates', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'bookshelf', 'sort_order' => 1, 'lexeme_id' => $dissimilarLexeme->id]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'jog', 'sort_order' => 2, 'lexeme_id' => $similarLexeme->id]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedLexemes(RecommendationLearner::fromUser($user), 10);

    expect($result->pluck('text')->all())->toBe(['jog', 'bookshelf']);
});

test('getRecommendedContents ranks content tied to an overdue review card ahead of one with none', function () {
    $user = User::factory()->create(['ui_language' => 'en']);

    $dueContent = Content::query()->create(['type' => 'song', 'title' => 'Overdue', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $dueContent->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'alpha', 'sort_order' => 1]);
    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $dueContent->id, 'item_key' => 'word:alpha',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5, 'next_review_at' => now()->subDay(),
    ]);

    $freshContent = Content::query()->create(['type' => 'song', 'title' => 'Fresh', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $freshContent->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'beta', 'sort_order' => 1]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedContents(RecommendationLearner::fromUser($user), 10);

    $ids = $result->pluck('id')->all();
    expect(array_search($dueContent->id, $ids, true))->toBeLessThan(array_search($freshContent->id, $ids, true));
});

test('getRecommendedContents ranks content matching the user CEFR level ahead of one two levels away', function () {
    $user = User::factory()->create(['ui_language' => 'en', 'current_level' => 'B1']);

    $matchingContent = Content::query()->create(['type' => 'song', 'title' => 'Matching', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'level' => 'B1']);
    $matchingContent->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'alpha', 'sort_order' => 1]);

    $farContent = Content::query()->create(['type' => 'song', 'title' => 'Far', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'level' => 'C2']);
    $farContent->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'beta', 'sort_order' => 1]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedContents(RecommendationLearner::fromUser($user), 10);

    $ids = $result->pluck('id')->all();
    expect(array_search($matchingContent->id, $ids, true))->toBeLessThan(array_search($farContent->id, $ids, true));
});

test('getRecommendedContents ignores level scoring when the user has no current_level set', function () {
    $user = User::factory()->create(['ui_language' => 'en', 'current_level' => null]);

    $content = Content::query()->create(['type' => 'song', 'title' => 'Any level', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready', 'level' => 'C2']);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'alpha', 'sort_order' => 1]);

    $service = app(RecommendationService::class);
    $result = $service->getRecommendedContents(RecommendationLearner::fromUser($user), 10);

    expect($result->pluck('id')->all())->toContain($content->id);
});
