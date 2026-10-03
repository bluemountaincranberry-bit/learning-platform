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

test('lexemes endpoint includes the canonical lexeme level', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    // ContentLexeme::booted() auto-syncs to a canonical lexeme on creation.
    $lexeme = Lexeme::query()->where('normalized_lemma', 'run')->where('language', 'en')->first();
    $lexeme->update(['level' => 'B1']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'run')['level'])->toBe('B1');
});

test('lexemes endpoint returns a null level when the canonical lexeme has none', function () {
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'jump', 'sort_order' => 1]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();

    $lexemes = collect($response->json('lexemes'));
    expect($lexemes->firstWhere('text', 'jump')['level'])->toBeNull();
});
