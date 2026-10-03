<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Application\Contracts\LearningProgressReferencesInterface;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('content reset can identify lexemes and content links protected by any learner progress', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $learnedLexeme = Lexeme::query()->create([
        'slug' => 'en-protected-'.uniqid(),
        'language' => 'en',
        'lemma' => 'protected',
        'normalized_lemma' => 'protected',
        'status' => 'published',
    ]);
    $unusedLexeme = Lexeme::query()->create([
        'slug' => 'en-unused-'.uniqid(),
        'language' => 'en',
        'lemma' => 'unused',
        'normalized_lemma' => 'unused',
        'status' => 'published',
    ]);
    $contentLexeme = $content->lexemes()->create([
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'protected',
        'sort_order' => 1,
        'lexeme_id' => $learnedLexeme->id,
    ]);
    UserLexemeProgress::query()->create(['user_id' => $user->id,
        'content_lexeme_id' => $contentLexeme->id,
        'lexeme_id' => $learnedLexeme->id,
        'learned_at' => now(),
    ]);

    $references = app(LearningProgressReferencesInterface::class);

    expect($references->referencedLexemeIds([$learnedLexeme->id, $unusedLexeme->id]))
        ->toBe([$learnedLexeme->id])
        ->and($references->hasContentLexeme($contentLexeme->id))->toBeTrue()
        ->and($references->hasContentLexeme($contentLexeme->id + 1))->toBeFalse();
});
