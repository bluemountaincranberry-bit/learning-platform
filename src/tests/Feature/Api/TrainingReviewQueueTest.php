<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeContentWithSyncedLexeme(string $text = 'run'): array
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $contentLexeme = $content->lexemes()->create(['type' => 'word', 'text' => $text, 'sort_order' => 1]);
    $lexeme = Lexeme::query()->where('normalized_lemma', $text)->where('language', 'en')->first();

    return [$content, $contentLexeme->fresh(), $lexeme];
}

test('review queue endpoint requires auth', function () {
    $this->getJson('/api/training/review-queue')->assertUnauthorized();
});

test('review queue endpoint enriches due cards with translation, examples and associations', function () {
    [$content, $contentLexeme, $lexeme] = makeContentWithSyncedLexeme('run');
    $lexeme->translations()->create(['language' => 'ru', 'translation' => 'бежать', 'is_primary' => true, 'sort_order' => 1]);
    $lexeme->examples()->create(['content_id' => null, 'language' => 'en', 'example' => 'I run every day.', 'translation' => 'Я бегаю каждый день.', 'is_primary' => true, 'sort_order' => 1]);
    $synonym = Lexeme::query()->create(['slug' => 'sprint', 'language' => 'en', 'lemma' => 'sprint', 'normalized_lemma' => 'sprint']);
    $lexeme->associations()->create(['related_lexeme_id' => $synonym->id, 'type' => 'synonym', 'sort_order' => 1]);

    $user = User::factory()->create(['translation_language' => 'ru']);
    $user->assignRole('user');

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:run',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/training/review-queue')
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $item = $response->json('items.0');
    expect($item['lexeme_display'])->toBe('run');
    expect($item['translation'])->toBe('бежать');
    expect($item['example'])->toBe('I run every day.');
    expect($item['associations'])->toBe([['lemma' => 'sprint', 'type' => 'synonym']]);
});

test('review queue endpoint returns null translation and example for an orphan card', function () {
    $content = Content::query()->create([
        'type' => 'book', 'title' => 'Orphan Source', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);

    $user = User::factory()->create();
    $user->assignRole('user');

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:orphan',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/training/review-queue')
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $item = $response->json('items.0');
    expect($item['lexeme_display'])->toBe('orphan');
    expect($item['translation'])->toBeNull();
    expect($item['example'])->toBeNull();
    expect($item['associations'])->toBe([]);
});

test('review queue returns a contentless canonical card without a source occurrence', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = Lexeme::query()->create([
        'slug' => 'personal-queue-'.uniqid(), 'language' => 'en', 'lemma' => 'sonder',
        'normalized_lemma' => 'sonder', 'owner_user_id' => $user->id,
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'content_id' => null,
        'item_key' => null, 'state' => 'reviewing', 'interval_days' => 2,
        'ease_factor' => 2.5, 'next_review_at' => now()->subMinute(),
    ]);

    $item = $this->actingAs($user)->getJson('/api/training/review-queue')
        ->assertOk()->assertJsonCount(1, 'items')->json('items.0');

    expect($item['lexeme_display'])->toBe('sonder')
        ->and($item['lexeme_id'])->toBe($lexeme->id)
        ->and($item['content_lexeme_id'])->toBeNull()
        ->and($item['content_id'])->toBeNull();
});

test('review queue endpoint filters by content_id', function () {
    [$contentA, , ] = makeContentWithSyncedLexeme('alpha');
    [$contentB, , ] = makeContentWithSyncedLexeme('beta');

    $user = User::factory()->create();
    $user->assignRole('user');

    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $contentA->id, 'item_key' => 'word:alpha',
        'state' => 'reviewing', 'interval_days' => 2, 'ease_factor' => 2.5, 'next_review_at' => now()->subMinute(),
    ]);
    SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $contentB->id, 'item_key' => 'word:beta',
        'state' => 'reviewing', 'interval_days' => 2, 'ease_factor' => 2.5, 'next_review_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/training/review-queue?content_id={$contentA->id}")
        ->assertOk()
        ->assertJsonCount(1, 'items');

    expect($response->json('items.0.lexeme_display'))->toBe('alpha');
});

test('selected lexemes endpoint returns focused words across contents', function () {
    [$contentA, $first] = makeContentWithSyncedLexeme('alpha');
    [$contentB, $second] = makeContentWithSyncedLexeme('beta');

    $user = User::factory()->create();
    $user->assignRole('user');

    $response = $this->actingAs($user)
        ->getJson("/api/training/selected-lexemes?lexeme_ids={$second->id},{$first->id}")
        ->assertOk()
        ->assertJsonCount(2, 'items');

    expect($response->json('items.0.content_id'))->toBe($contentB->id);
    expect($response->json('items.0.content_lexeme_id'))->toBe($second->id);
    expect($response->json('items.0.lexeme_display'))->toBe('beta');
    expect($response->json('items.1.content_id'))->toBe($contentA->id);
    expect($response->json('items.1.content_lexeme_id'))->toBe($first->id);
    expect($response->json('items.1.lexeme_display'))->toBe('alpha');
});
