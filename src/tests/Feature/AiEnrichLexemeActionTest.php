<?php

use App\Filament\Resources\Lexemes\Pages\EditLexeme;
use App\Modules\Ai\Application\AiFieldEditService;
use App\Modules\Ai\Application\LexemeEnrichmentService;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeAssociation;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use Livewire\Livewire;

function mockLexemeEnrichmentAiProposal(array $proposal): void
{
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldReceive('proposePrepared')->once()->andReturn($proposal);
    app()->instance(AiFieldEditService::class, $mock);
}

test('AI enrich action pre-fills the modal without saving anything', function () {
    config(['ai.enabled' => true]);
    actingAdminForLexemes();
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    mockLexemeEnrichmentAiProposal([
        'related' => [['lemma' => 'dressing gown', 'type' => 'synonym', 'gloss' => 'UK term']],
        'examples' => [['example' => 'He put on his bathrobe.', 'translation' => 'Он надел халат.']],
        'translation' => 'халат',
    ]);

    Livewire::test(EditLexeme::class, ['record' => $lexeme->getRouteKey()])
        ->mountAction('aiEnrichLexeme')
        ->assertActionMounted('aiEnrichLexeme');

    expect(LexemeAssociation::query()->count())->toBe(0);
    expect(LexemeExample::query()->count())->toBe(0);
    expect(LexemeTranslation::query()->count())->toBe(0);
});

test('accepting the default proposal creates a typed relation, an example and a translation', function () {
    config(['ai.enabled' => true]);
    actingAdminForLexemes();
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    mockLexemeEnrichmentAiProposal([
        'related' => [['lemma' => 'dressing gown', 'type' => 'synonym', 'gloss' => 'UK term']],
        'examples' => [['example' => 'He put on his bathrobe.', 'translation' => 'Он надел халат.']],
        'translation' => 'халат',
    ]);

    Livewire::test(EditLexeme::class, ['record' => $lexeme->getRouteKey()])
        ->mountAction('aiEnrichLexeme')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $newLexeme = Lexeme::query()->where('normalized_lemma', 'dressing gown')->first();
    expect($newLexeme)->not->toBeNull()->and($newLexeme->status)->toBe(Lexeme::STATUS_DRAFT);
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->where('related_lexeme_id', $newLexeme->id)->where('type', 'synonym')->exists())->toBeTrue();

    $example = LexemeExample::query()->where('lexeme_id', $lexeme->id)->first();
    expect($example)->not->toBeNull()
        ->and($example->example)->toBe('He put on his bathrobe.')
        ->and($example->is_primary)->toBeTrue();

    $translation = LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->first();
    expect($translation)->not->toBeNull()
        ->and($translation->translation)->toBe('халат')
        ->and($translation->language)->toBe('ru')
        ->and($translation->is_primary)->toBeTrue();
});

test('propose matches an AI-suggested related lemma against an existing lexeme instead of a new one', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);
    $existing = Lexeme::query()->create(['slug' => 'robe', 'language' => 'en', 'lemma' => 'robe', 'normalized_lemma' => 'robe']);

    mockLexemeEnrichmentAiProposal([
        'related' => [['lemma' => 'robe', 'type' => 'synonym', 'gloss' => 'general term']],
        'examples' => [],
        'translation' => null,
    ]);

    $proposal = app(LexemeEnrichmentService::class)->propose($lexeme->id);

    expect($proposal['related'])->toHaveCount(1)
        ->and($proposal['related'][0]['type'])->toBe('synonym')
        ->and($proposal['related'][0]['matched_lexeme_id'])->toBe($existing->id)
        ->and($proposal['related'][0]['match_score'])->toBe(1.0);
});

test('propose defaults an unrecognized or missing relation type to "related"', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    mockLexemeEnrichmentAiProposal([
        'related' => [
            ['lemma' => 'robe', 'type' => 'not-a-real-type'],
            ['lemma' => 'gown'],
        ],
        'examples' => [],
        'translation' => null,
    ]);

    $proposal = app(LexemeEnrichmentService::class)->propose($lexeme->id);

    expect($proposal['related'][0]['type'])->toBe('related')
        ->and($proposal['related'][1]['type'])->toBe('related');
});

test('applyAccepted only persists rows the caller marked accepted', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    app(LexemeEnrichmentService::class)->applyAccepted($lexeme->id, [
        'related' => [
            ['lemma' => 'dressing gown', 'type' => 'synonym', 'matched_lexeme_id' => null, 'accept' => false],
            ['lemma' => 'robe', 'type' => 'synonym', 'matched_lexeme_id' => null, 'accept' => true],
        ],
        'examples' => [
            ['example' => 'Rejected example.', 'translation' => null, 'accept' => false],
            ['example' => 'Accepted example.', 'translation' => 'Принятый пример.', 'accept' => true],
        ],
        'translations' => [
            ['language' => 'ru', 'translation' => 'халат', 'accept' => true],
        ],
    ]);

    expect(Lexeme::query()->where('normalized_lemma', 'dressing gown')->exists())->toBeFalse();
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
    $created = Lexeme::query()->where('normalized_lemma', 'robe')->first();
    expect($created)->not->toBeNull()->and($created->status)->toBe(Lexeme::STATUS_DRAFT);

    expect(LexemeExample::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
    expect(LexemeExample::query()->where('lexeme_id', $lexeme->id)->first()->example)->toBe('Accepted example.');

    expect(LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
});

test('a related lemma that already matches an existing lexeme links to it instead of creating a duplicate', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);
    $existing = Lexeme::query()->create(['slug' => 'robe', 'language' => 'en', 'lemma' => 'robe', 'normalized_lemma' => 'robe']);

    $countBefore = Lexeme::query()->count();

    app(LexemeEnrichmentService::class)->applyAccepted($lexeme->id, [
        'related' => [['lemma' => 'robe', 'type' => 'synonym', 'matched_lexeme_id' => $existing->id, 'accept' => true]],
    ]);

    expect(Lexeme::query()->count())->toBe($countBefore);
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->where('related_lexeme_id', $existing->id)->where('type', 'synonym')->exists())->toBeTrue();
});

test('applyAccepted persists the AI-proposed type, not a hardcoded synonym', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run']);

    app(LexemeEnrichmentService::class)->applyAccepted($lexeme->id, [
        'related' => [['lemma' => 'run into', 'type' => 'phrasal_verb', 'matched_lexeme_id' => null, 'accept' => true]],
    ]);

    $created = Lexeme::query()->where('normalized_lemma', 'run into')->first();
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->where('related_lexeme_id', $created->id)->where('type', 'phrasal_verb')->exists())->toBeTrue();
});
