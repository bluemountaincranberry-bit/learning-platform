<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;

test('show returns the flat translations/examples/associations for a sense-less lexeme (backward compat)', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    $word = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'part_of_speech' => 'verb',
    ]);
    $word->translations()->create(['language' => 'ru', 'translation' => 'бежать', 'is_primary' => true]);
    $word->examples()->create(['language' => 'en', 'example' => 'I run every day.', 'translation' => 'Я бегаю каждый день.', 'is_primary' => true]);

    $response = $this->actingAs($user)->getJson("/api/dictionary/{$word->id}")->assertOk();

    expect($response->json('lexeme.lemma'))->toBe('run')
        ->and($response->json('lexeme.translations.0.translation'))->toBe('бежать')
        ->and($response->json('lexeme.examples.0.example'))->toBe('I run every day.')
        ->and($response->json('lexeme.senses'))->toBe([])
        ->and($response->json('lexeme.forms'))->toBe([]);
});

test('show groups translations/examples by sense, alongside the flat sense-less arrays (task 10.5)', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    $word = Lexeme::query()->create([
        'slug' => 'en-bank', 'language' => 'en', 'lemma' => 'bank', 'normalized_lemma' => 'bank',
    ]);
    $financial = $word->senses()->create(['part_of_speech' => 'noun', 'gloss' => 'financial institution', 'normalized_gloss' => 'financial institution']);
    $riverbank = $word->senses()->create(['part_of_speech' => 'noun', 'gloss' => 'riverbank', 'normalized_gloss' => 'riverbank']);

    $financial->translations()->create(['lexeme_id' => $word->id, 'language' => 'ru', 'translation' => 'банк', 'is_primary' => true]);
    $financial->examples()->create(['lexeme_id' => $word->id, 'language' => 'en', 'example' => 'I went to the bank.', 'translation' => 'Я пошёл в банк.', 'is_primary' => true]);
    $riverbank->translations()->create(['lexeme_id' => $word->id, 'language' => 'ru', 'translation' => 'берег', 'is_primary' => true]);

    $response = $this->actingAs($user)->getJson("/api/dictionary/{$word->id}")->assertOk();

    $senses = $response->json('lexeme.senses');
    expect($senses)->toHaveCount(2);
    $financialRow = collect($senses)->firstWhere('gloss', 'financial institution');
    $riverbankRow = collect($senses)->firstWhere('gloss', 'riverbank');
    expect($financialRow['translations'][0]['translation'])->toBe('банк')
        ->and($financialRow['examples'][0]['example'])->toBe('I went to the bank.')
        ->and($riverbankRow['translations'][0]['translation'])->toBe('берег')
        ->and($riverbankRow['examples'])->toBe([]);
});

test('show lists distinct observed forms with their grammar features (task 10.5)', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);
    $word = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run',
    ]);
    $content = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $content->lexemes()->create(['type' => 'word', 'text' => 'ran', 'sort_order' => 0, 'lexeme_id' => $word->id, 'grammar_features' => ['tense' => 'past']]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'running', 'sort_order' => 1, 'lexeme_id' => $word->id, 'grammar_features' => ['aspect' => 'progressive']]);
    // Same form seen twice (e.g. two different pieces of content) collapses to one entry.
    $content2 = Content::factory()->create(['status' => 'ready', 'language' => 'en']);
    $content2->lexemes()->create(['type' => 'word', 'text' => 'Ran', 'sort_order' => 0, 'lexeme_id' => $word->id, 'grammar_features' => ['tense' => 'past']]);

    $response = $this->actingAs($user)->getJson("/api/dictionary/{$word->id}")->assertOk();

    $forms = $response->json('lexeme.forms');
    expect($forms)->toHaveCount(2);
    $ran = collect($forms)->firstWhere('text', 'ran');
    expect($ran['grammar_features'])->toBe(['tense' => 'past']);
});
