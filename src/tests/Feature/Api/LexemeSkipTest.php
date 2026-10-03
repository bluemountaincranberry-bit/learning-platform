<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeSkip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeSkipTestLexeme(): App\Modules\Content\Domain\Models\ContentLexeme
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    return $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
}

test('skip requires auth', function () {
    $lexeme = makeSkipTestLexeme();

    $this->postJson("/api/content/lexemes/{$lexeme->id}/skip")->assertUnauthorized();
});

test('authenticated user can skip a lexeme', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = makeSkipTestLexeme();

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/skip")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(UserLexemeSkip::query()->where('user_id', $user->id)->where('lexeme_id', $lexeme->lexeme_id)->exists())->toBeTrue();
});

test('skip is idempotent', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = makeSkipTestLexeme();

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/skip")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/skip")->assertOk();

    expect(UserLexemeSkip::query()->where('user_id', $user->id)->where('lexeme_id', $lexeme->lexeme_id)->count())->toBe(1);
});

test('unskip removes the skip marker', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = makeSkipTestLexeme();

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/skip")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/unskip")->assertOk();

    expect(UserLexemeSkip::query()->where('user_id', $user->id)->where('lexeme_id', $lexeme->lexeme_id)->exists())->toBeFalse();
});

test('get content lexemes returns the skipped flag, scoped per user', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexeme1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    UserLexemeSkip::query()->create(['user_id' => $user->id, 'lexeme_id' => $lexeme1->lexeme_id]);

    $this->actingAs($user)
        ->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.text', 'hello')
        ->assertJsonPath('lexemes.0.skipped', true)
        ->assertJsonPath('lexemes.1.text', 'world')
        ->assertJsonPath('lexemes.1.skipped', false);

    // The other user never skipped it — must not see it as skipped.
    $this->actingAs($otherUser)
        ->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.skipped', false);
});
