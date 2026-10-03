<?php

use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Interfaces\Jobs\SuggestLexemeLevelJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function makeLexeme(string $lemma, ?string $level): Lexeme
{
    return Lexeme::query()->create([
        'slug' => 'en-'.$lemma,
        'language' => 'en',
        'lemma' => $lemma,
        'normalized_lemma' => $lemma,
        'status' => Lexeme::STATUS_REVIEW,
        'level' => $level,
    ]);
}

test('--all dispatches only for lexemes missing a level', function () {
    Queue::fake();
    $withLevel = makeLexeme('known', 'A1');
    $withoutLevel = makeLexeme('unknown', null);

    $this->artisan('ai:backfill-lexeme-levels', ['--all' => true])->assertSuccessful();

    Queue::assertPushed(SuggestLexemeLevelJob::class, 1);
    Queue::assertPushed(SuggestLexemeLevelJob::class, fn ($job) => $job->lexemeId === $withoutLevel->id);
});

test('--ids skips ids that already have a level', function () {
    Queue::fake();
    $withLevel = makeLexeme('known2', 'B2');
    $withoutLevel = makeLexeme('unknown2', null);

    $this->artisan('ai:backfill-lexeme-levels', ['--ids' => "{$withLevel->id},{$withoutLevel->id}"])->assertSuccessful();

    Queue::assertPushed(SuggestLexemeLevelJob::class, 1);
    Queue::assertPushed(SuggestLexemeLevelJob::class, fn ($job) => $job->lexemeId === $withoutLevel->id);
});

test('requires --all or --ids', function () {
    $this->artisan('ai:backfill-lexeme-levels')->assertFailed();
});

test('is a no-op when nothing is missing a level', function () {
    Queue::fake();
    makeLexeme('alreadyleveled', 'C1');

    $this->artisan('ai:backfill-lexeme-levels', ['--all' => true])->assertSuccessful();

    Queue::assertNotPushed(SuggestLexemeLevelJob::class);
});
