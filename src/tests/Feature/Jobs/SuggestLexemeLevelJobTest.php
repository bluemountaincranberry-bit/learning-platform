<?php

use App\Contracts\Ai\LexemeMetadataSuggestionCapability;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Jobs\SuggestLexemeLevelJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeLexemeWithoutLevel(string $lemma, ?string $partOfSpeech = null): Lexeme
{
    return Lexeme::query()->create([
        'slug' => 'en-'.$lemma,
        'language' => 'en',
        'lemma' => $lemma,
        'normalized_lemma' => $lemma,
        'status' => Lexeme::STATUS_REVIEW,
        'level' => null,
        'part_of_speech' => $partOfSpeech,
    ]);
}

function fakeMetadataResponse(?string $level, ?string $partOfSpeech): void
{
    $content = json_encode(array_filter([
        'level' => $level,
        'part_of_speech' => $partOfSpeech,
    ], fn ($v) => $v !== null));

    Http::fake([
        'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => $content]]]], 200),
    ]);
}

beforeEach(function () {
    config([
        'ai.enabled' => true,
        'ai.lexeme_metadata_suggestion.enabled' => true,
        'ai.provider' => 'openai',
        'ai.openai.api_key' => 'test-key',
    ]);
});

test('job suggests and stores both level and part_of_speech for a lexeme missing both', function () {
    fakeMetadataResponse('B1', 'verb');
    $lexeme = makeLexemeWithoutLevel('run');

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBe('B1')
        ->and($fresh->part_of_speech)->toBe('verb');
});

test('job fills only the missing field when the other is already set', function () {
    fakeMetadataResponse('B1', 'verb');
    $lexeme = makeLexemeWithoutLevel('walk', 'verb');
    // part_of_speech already curated; only level is missing.

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBe('B1')
        ->and($fresh->part_of_speech)->toBe('verb');
});

test('job does not overwrite an existing level or part_of_speech and makes no AI call once both are set', function () {
    Http::fake();
    Http::preventStrayRequests();
    $lexeme = makeLexemeWithoutLevel('walk', 'verb');
    $lexeme->update(['level' => 'A2']);

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBe('A2')
        ->and($fresh->part_of_speech)->toBe('verb');
});

test('job is a no-op when the general AI feature flag is disabled', function () {
    config(['ai.enabled' => false]);
    Http::fake();
    Http::preventStrayRequests();
    $lexeme = makeLexemeWithoutLevel('sit');

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBeNull()
        ->and($fresh->part_of_speech)->toBeNull();
});

test('job is a no-op when the lexeme_metadata_suggestion flag is disabled, even if the general AI flag is on', function () {
    config(['ai.lexeme_metadata_suggestion.enabled' => false]);
    Http::fake();
    Http::preventStrayRequests();
    $lexeme = makeLexemeWithoutLevel('stand');

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBeNull()
        ->and($fresh->part_of_speech)->toBeNull();
});

test('job ignores an unrecognized level but still stores a valid part_of_speech', function () {
    fakeMetadataResponse('not-a-level', 'noun');
    $lexeme = makeLexemeWithoutLevel('eat');

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBeNull()
        ->and($fresh->part_of_speech)->toBe('noun');
});

test('job ignores an unrecognized part_of_speech but still stores a valid level', function () {
    fakeMetadataResponse('B2', 'not-a-part-of-speech');
    $lexeme = makeLexemeWithoutLevel('drink');

    (new SuggestLexemeLevelJob($lexeme->id))->handle(app(LexemeMetadataSuggestionCapability::class));

    $fresh = $lexeme->fresh();
    expect($fresh->level)->toBe('B2')
        ->and($fresh->part_of_speech)->toBeNull();
});

test('job is a no-op for a lexeme id that no longer exists', function () {
    Http::fake();
    Http::preventStrayRequests();

    (new SuggestLexemeLevelJob(999999))->handle(app(LexemeMetadataSuggestionCapability::class));

    expect(Lexeme::query()->count())->toBe(0);
});
