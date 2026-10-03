<?php

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeSense;
use App\Modules\Content\Application\LexemeSenseSyncService;

test('sync returns null and creates nothing when no gloss is given', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);

    $sense = app(LexemeSenseSyncService::class)->sync($lexeme, null);

    expect($sense)->toBeNull();
    expect(LexemeSense::query()->count())->toBe(0);
});

test('sync creates a new sense with its gloss and part_of_speech', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);

    $sense = app(LexemeSenseSyncService::class)->sync($lexeme, 'move quickly on foot', 'verb');

    expect($sense)->not->toBeNull()
        ->and($sense->lexeme_id)->toBe($lexeme->id)
        ->and($sense->gloss)->toBe('move quickly on foot')
        ->and($sense->normalized_gloss)->toBe('move quickly on foot')
        ->and($sense->part_of_speech)->toBe('verb');
});

test('sync reuses an existing sense on a second call with the same gloss, case/whitespace-insensitive', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $service = app(LexemeSenseSyncService::class);

    $first = $service->sync($lexeme, 'move quickly on foot');
    $second = $service->sync($lexeme, '  Move Quickly On Foot  ');

    expect($second->id)->toBe($first->id);
    expect(LexemeSense::query()->where('lexeme_id', $lexeme->id)->count())->toBe(1);
});

test('sync creates a distinct sense for a genuinely different gloss on the same lexeme', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $service = app(LexemeSenseSyncService::class);

    $moveOnFoot = $service->sync($lexeme, 'move quickly on foot');
    $manageBusiness = $service->sync($lexeme, 'manage or operate a business');

    expect($moveOnFoot->id)->not->toBe($manageBusiness->id);
    expect(LexemeSense::query()->where('lexeme_id', $lexeme->id)->count())->toBe(2);
});

test('sync backfills part_of_speech on an existing sense that has none yet', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $service = app(LexemeSenseSyncService::class);

    $sense = $service->sync($lexeme, 'move quickly on foot');
    expect($sense->part_of_speech)->toBeNull();

    $sense = $service->sync($lexeme, 'move quickly on foot', 'verb');
    expect($sense->part_of_speech)->toBe('verb');
});

test('sync never overwrites an already-curated part_of_speech on an existing sense', function () {
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $service = app(LexemeSenseSyncService::class);

    $service->sync($lexeme, 'move quickly on foot', 'verb');
    $sense = $service->sync($lexeme, 'move quickly on foot', 'noun');

    expect($sense->part_of_speech)->toBe('verb');
});

test('the same gloss on two different lexemes creates two independent senses', function () {
    $run = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $jog = Lexeme::query()->create([
        'slug' => 'en-jog', 'language' => 'en', 'lemma' => 'jog', 'normalized_lemma' => 'jog', 'status' => Lexeme::STATUS_REVIEW,
    ]);
    $service = app(LexemeSenseSyncService::class);

    $runSense = $service->sync($run, 'move quickly on foot');
    $jogSense = $service->sync($jog, 'move quickly on foot');

    expect($runSense->id)->not->toBe($jogSense->id);
});
