<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('lexemes endpoint includes this occurrence grammar_features and sense_gloss (task 10.5)', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run',
    ]);
    $sense = $lexeme->senses()->create(['part_of_speech' => 'verb', 'gloss' => 'move quickly on foot', 'normalized_gloss' => 'move quickly on foot']);
    $content->lexemes()->create([
        'type' => 'word', 'text' => 'ran', 'sort_order' => 1, 'lexeme_id' => $lexeme->id,
        'lexeme_sense_id' => $sense->id, 'grammar_features' => ['tense' => 'past'],
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    $ran = $lexemes->firstWhere('text', 'ran');
    expect($ran['grammar_features'])->toBe(['tense' => 'past'])
        ->and($ran['sense_gloss'])->toBe('move quickly on foot');
});

test('lexemes endpoint returns null grammar_features and sense_gloss for a tag-less occurrence', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'jump', 'sort_order' => 1]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    $jump = $lexemes->firstWhere('text', 'jump');
    expect($jump['grammar_features'])->toBeNull()
        ->and($jump['sense_gloss'])->toBeNull();
});
