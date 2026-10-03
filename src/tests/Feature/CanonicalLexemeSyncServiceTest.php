<?php

use App\Modules\Ai\Interfaces\Jobs\EnrichLexemeAssociationsJob;
use App\Modules\Content\Application\CanonicalLexemeSyncService;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Jobs\SuggestLexemeLevelJob;
use Illuminate\Support\Facades\Queue;

test('sync disambiguates lexemes whose slugs collide after Str::slug strips punctuation', function () {
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'draft',
    ]);

    // "we're" and "were" are different words (different normalized_lemma) but
    // Str::slug() strips the apostrophe, so both naively produce "en-were".
    $contraction = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => "we're",
        'sort_order' => 1,
    ]);
    $plainWord = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'were',
        'sort_order' => 2,
    ]);

    expect($contraction->fresh()->lexeme_id)->not->toBeNull();
    expect($plainWord->fresh()->lexeme_id)->not->toBeNull();
    expect($contraction->fresh()->lexeme_id)->not->toBe($plainWord->fresh()->lexeme_id);

    $slugs = Lexeme::query()->whereIn('normalized_lemma', ["we're", 'were'])->pluck('slug')->sort()->values();
    expect($slugs->all())->toBe(['en-were', 'en-were-2']);
});

test('sync dispatches SuggestLexemeLevelJob when it creates a brand-new lexeme with no level (task 7.2)', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'draft',
    ]);
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD, 'text' => 'brandnewword', 'sort_order' => 1,
    ]);

    $lexemeId = $contentLexeme->fresh()->lexeme_id;
    Queue::assertPushed(SuggestLexemeLevelJob::class, fn ($job) => $job->lexemeId === $lexemeId);
    Queue::assertPushed(SuggestLexemeLevelJob::class, 1);
});

test('sync does not dispatch again for a lexeme that already exists', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'draft',
    ]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'duplicateword', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'duplicateword', 'sort_order' => 2]);

    Queue::assertPushed(SuggestLexemeLevelJob::class, 1);
});

test('sync does not dispatch for a lexeme that already has a level', function () {
    Queue::fake();

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Test', 'language' => 'en', 'origin' => 'curated', 'status' => 'draft',
    ]);
    Lexeme::query()->create([
        'slug' => 'en-leveledword', 'language' => 'en', 'lemma' => 'leveledword',
        'normalized_lemma' => 'leveledword', 'status' => Lexeme::STATUS_PUBLISHED, 'level' => 'A1',
    ]);
    $content->lexemes()->create(['type' => ContentLexeme::TYPE_WORD, 'text' => 'leveledword', 'sort_order' => 1]);

    Queue::assertNotPushed(SuggestLexemeLevelJob::class);
});

test('syncLemma creates a canonical lexeme from an explicit lemma, independent of any occurrence text (task 10.1)', function () {
    $lexeme = app(CanonicalLexemeSyncService::class)->syncLemma('en', 'run');

    expect($lexeme->lemma)->toBe('run')
        ->and($lexeme->normalized_lemma)->toBe('run')
        ->and(Lexeme::query()->where('normalized_lemma', 'run')->count())->toBe(1);
});

test('syncLemma reuses the existing lexeme on a second call for the same normalized lemma', function () {
    $service = app(CanonicalLexemeSyncService::class);
    $first = $service->syncLemma('en', 'run');
    $second = $service->syncLemma('en', 'Run');

    expect($second->id)->toBe($first->id)
        ->and(Lexeme::query()->where('normalized_lemma', 'run')->count())->toBe(1);
});

test('syncLemma sets part_of_speech on a newly created lexeme when given', function () {
    $lexeme = app(CanonicalLexemeSyncService::class)->syncLemma('en', 'swim', 'verb');

    expect($lexeme->part_of_speech)->toBe('verb');
});

test('syncLemma dispatches EnrichLexemeAssociationsJob when it creates a brand-new lexeme (task 10.4)', function () {
    Queue::fake();

    $lexeme = app(CanonicalLexemeSyncService::class)->syncLemma('en', 'sprint');

    Queue::assertPushed(EnrichLexemeAssociationsJob::class, fn ($job) => $job->lexemeId === $lexeme->id);
    Queue::assertPushed(EnrichLexemeAssociationsJob::class, 1);
});

test('syncLemma does not dispatch EnrichLexemeAssociationsJob again for an existing lexeme', function () {
    Queue::fake();
    $service = app(CanonicalLexemeSyncService::class);
    $service->syncLemma('en', 'sprint');
    Queue::fake(); // reset the count from the creation above

    $service->syncLemma('en', 'Sprint');

    Queue::assertNotPushed(EnrichLexemeAssociationsJob::class);
});
