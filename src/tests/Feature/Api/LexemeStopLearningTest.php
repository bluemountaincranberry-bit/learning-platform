<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function createReadyContentWithLexemeForStopLearning(string $text = 'hello'): array
{
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $content->lexemes()->create([
        'type' => 'word',
        'text' => $text,
        'sort_order' => 1,
    ]);

    return [$content, $lexeme];
}

test('stop learning requires auth', function () {
    [, $lexeme] = createReadyContentWithLexemeForStopLearning();

    $this->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")
        ->assertUnauthorized();
});

test('authenticated user can stop learning a lexeme, deleting its SrsCard', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    expect(SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->exists())->toBeTrue();

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->exists())->toBeFalse();
});

test('stop learning is a no-op when the lexeme was never started', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")
        ->assertOk()
        ->assertJson(['ok' => true]);
});

test('stop learning only removes this user\'s card, not other users\' cards for the same word', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    $this->actingAs($otherUser)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")->assertOk();

    expect(SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->exists())->toBeFalse();
    expect(SrsCard::query()->where('user_id', $otherUser->id)->where('item_key', 'word:hello')->exists())->toBeTrue();
});

test('content lexemes endpoint reports in_review false after stopping learning', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [$content, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")->assertOk();

    $after = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->json('lexemes');
    expect(collect($after)->firstWhere('id', $lexeme->id)['in_review'])->toBeFalse();
});
