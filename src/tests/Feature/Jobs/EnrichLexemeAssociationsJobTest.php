<?php

use App\Exceptions\AiClientException;
use App\Modules\Ai\Application\AiFieldEditService;
use App\Modules\Ai\Application\LexemeEnrichmentService;
use App\Modules\Ai\Interfaces\Jobs\EnrichLexemeAssociationsJob;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeAssociation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeLexemeForEnrichment(string $lemma = 'run'): Lexeme
{
    return Lexeme::query()->create([
        'slug' => 'en-'.$lemma, 'language' => 'en', 'lemma' => $lemma, 'normalized_lemma' => $lemma, 'status' => Lexeme::STATUS_REVIEW,
    ]);
}

function fakeEnrichmentProposal(array $related): void
{
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldReceive('proposePrepared')->once()->andReturn([
        'related' => $related,
        'examples' => [],
        'translation' => null,
    ]);
    app()->instance(AiFieldEditService::class, $mock);
}

beforeEach(function () {
    config(['ai.enabled' => true, 'ai.lexeme_relations_enrichment.enabled' => true]);
});

test('job persists proposed related words automatically, without a human accepting first', function () {
    $lexeme = makeLexemeForEnrichment();
    fakeEnrichmentProposal([
        ['lemma' => 'jog', 'type' => 'near_synonym', 'gloss' => 'lighter pace'],
        ['lemma' => 'sprint', 'type' => 'near_synonym', 'gloss' => 'much faster'],
    ]);

    (new EnrichLexemeAssociationsJob($lexeme->id))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(2);
    $jog = Lexeme::query()->where('normalized_lemma', 'jog')->first();
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->where('related_lexeme_id', $jog->id)->where('type', 'near_synonym')->exists())->toBeTrue();
});

test('job caps the number of persisted relations at the configured max', function () {
    config(['ai.lexeme_relations_enrichment.max_per_lexeme' => 2]);
    $lexeme = makeLexemeForEnrichment();
    fakeEnrichmentProposal([
        ['lemma' => 'jog', 'type' => 'near_synonym'],
        ['lemma' => 'sprint', 'type' => 'near_synonym'],
        ['lemma' => 'dash', 'type' => 'near_synonym'],
    ]);

    (new EnrichLexemeAssociationsJob($lexeme->id))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(2);
});

test('job is a no-op when the general AI feature flag is disabled', function () {
    config(['ai.enabled' => false]);
    $lexeme = makeLexemeForEnrichment();
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldNotReceive('proposePrepared');
    app()->instance(AiFieldEditService::class, $mock);

    (new EnrichLexemeAssociationsJob($lexeme->id))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->count())->toBe(0);
});

test('job is a no-op when the lexeme_relations_enrichment flag is disabled, even if the general AI flag is on', function () {
    config(['ai.lexeme_relations_enrichment.enabled' => false]);
    $lexeme = makeLexemeForEnrichment();
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldNotReceive('proposePrepared');
    app()->instance(AiFieldEditService::class, $mock);

    (new EnrichLexemeAssociationsJob($lexeme->id))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->count())->toBe(0);
});

test('job is a no-op for a lexeme id that no longer exists', function () {
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldNotReceive('proposePrepared');
    app()->instance(AiFieldEditService::class, $mock);

    (new EnrichLexemeAssociationsJob(999999))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->count())->toBe(0);
});

test('job swallows an AI client failure instead of throwing (best-effort)', function () {
    $lexeme = makeLexemeForEnrichment();
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldReceive('proposePrepared')->once()->andThrow(new AiClientException('boom'));
    app()->instance(AiFieldEditService::class, $mock);

    (new EnrichLexemeAssociationsJob($lexeme->id))->handle(app(LexemeEnrichmentService::class));

    expect(LexemeAssociation::query()->count())->toBe(0);
});
