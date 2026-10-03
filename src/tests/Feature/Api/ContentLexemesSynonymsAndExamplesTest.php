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

function makeContentWithLexeme(string $text = 'run'): array
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $content->lexemes()->create(['type' => 'word', 'text' => $text, 'sort_order' => 1]);
    $lexeme = Lexeme::query()->where('normalized_lemma', $text)->where('language', 'en')->first();

    return [$content, $lexeme];
}

test('lexemes endpoint returns associations of all types', function () {
    [$content, $lexeme] = makeContentWithLexeme('run');
    $sprint = Lexeme::query()->create(['slug' => 'sprint', 'language' => 'en', 'lemma' => 'sprint', 'normalized_lemma' => 'sprint']);
    $walk = Lexeme::query()->create(['slug' => 'walk', 'language' => 'en', 'lemma' => 'walk', 'normalized_lemma' => 'walk']);
    $lexeme->associations()->create(['related_lexeme_id' => $sprint->id, 'type' => 'synonym', 'sort_order' => 1]);
    $lexeme->associations()->create(['related_lexeme_id' => $walk->id, 'type' => 'antonym', 'sort_order' => 2]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    $associations = collect($response->json('lexemes'))->firstWhere('text', 'run')['associations'];

    expect($associations)->toBe([
        ['lemma' => 'sprint', 'type' => 'synonym'],
        ['lemma' => 'walk', 'type' => 'antonym'],
    ]);
});

test('lexemes endpoint orders examples content-scoped first then global by sort order', function () {
    [$content, $lexeme] = makeContentWithLexeme('run');
    $lexeme->examples()->create(['content_id' => null, 'language' => 'en', 'example' => 'global two', 'sort_order' => 2]);
    $lexeme->examples()->create(['content_id' => $content->id, 'language' => 'en', 'example' => 'scoped', 'sort_order' => 5]);
    $lexeme->examples()->create(['content_id' => null, 'language' => 'en', 'example' => 'global one', 'sort_order' => 1]);

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    $examples = collect($response->json('lexemes'))->firstWhere('text', 'run')['examples'];

    expect(array_column($examples, 'example'))->toBe(['scoped', 'global one', 'global two']);
});

test('lexemes endpoint caps examples at three', function () {
    [$content, $lexeme] = makeContentWithLexeme('run');
    foreach (range(1, 5) as $i) {
        $lexeme->examples()->create(['content_id' => null, 'language' => 'en', 'example' => "example {$i}", 'sort_order' => $i]);
    }

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    $examples = collect($response->json('lexemes'))->firstWhere('text', 'run')['examples'];

    expect($examples)->toHaveCount(3);
    expect(array_column($examples, 'example'))->toBe(['example 1', 'example 2', 'example 3']);
});

test('lexemes endpoint returns empty arrays when a lexeme has no examples or associations', function () {
    [$content] = makeContentWithLexeme('run');

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->assertOk();
    $lexeme = collect($response->json('lexemes'))->firstWhere('text', 'run');

    expect($lexeme['examples'])->toBe([]);
    expect($lexeme['associations'])->toBe([]);
});
