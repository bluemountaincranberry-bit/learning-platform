<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
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

test('stopping learning deactivates its SrsCard and preserves review history', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    expect(SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->exists())->toBeTrue();
    $card = SrsCard::query()->where('user_id', $user->id)->firstOrFail();
    $review = SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 4, 'prev_interval' => 1, 'new_interval' => 2]);
    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")
        ->assertOk()
        ->assertJson(['ok' => true]);

    $card->refresh();
    expect($card->deactivated_at)->not->toBeNull();
    expect(SrsReview::query()->whereKey($review->id)->exists())->toBeTrue();
});

test('restarting learning reactivates its card without resetting schedule or reviews', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    $card = SrsCard::query()->where('user_id', $user->id)->firstOrFail();
    $scheduledAt = now()->addDays(9)->startOfSecond();
    $card->update(['state' => 'reviewing', 'interval_days' => 9, 'next_review_at' => $scheduledAt]);
    $review = SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 4, 'prev_interval' => 3, 'new_interval' => 9]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    $card->refresh();
    expect($card->deactivated_at)->toBeNull();
    expect($card->state)->toBe('reviewing');
    expect($card->interval_days)->toBe(9);
    expect($card->next_review_at->equalTo($scheduledAt))->toBeTrue();
    expect(SrsReview::query()->whereKey($review->id)->exists())->toBeTrue();
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

test('stop learning deactivates only this user\'s card', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $otherUser = User::factory()->create();
    $otherUser->assignRole('user');
    [, $lexeme] = createReadyContentWithLexemeForStopLearning('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    $this->actingAs($otherUser)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/stop-learning")->assertOk();

    $card = SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->firstOrFail();
    expect($card->deactivated_at)->not->toBeNull();
    expect(SrsCard::query()->where('user_id', $otherUser->id)->where('item_key', 'word:hello')->value('deactivated_at'))->toBeNull();
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
