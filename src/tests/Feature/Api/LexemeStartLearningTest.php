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

function createReadyContentWithLexeme(string $text = 'hello'): array
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

test('start learning requires auth', function () {
    [, $lexeme] = createReadyContentWithLexeme();

    $this->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")
        ->assertUnauthorized();
});

test('authenticated user can start learning a lexeme, creating a due SrsCard', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [$content, $lexeme] = createReadyContentWithLexeme('hello');

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")
        ->assertOk()
        ->assertJson(['ok' => true]);

    $card = SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->first();

    expect($card)->not->toBeNull();
    expect($card->content_id)->toBe($content->id);
    expect($card->state)->toBe('new');
    expect($card->next_review_at->lessThanOrEqualTo(now()))->toBeTrue();
});

test('start learning is idempotent', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexeme('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    expect(SrsCard::query()->where('user_id', $user->id)->where('item_key', 'word:hello')->count())->toBe(1);
});

test('content lexemes endpoint reports in_review after starting learning', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [$content, $lexeme] = createReadyContentWithLexeme('hello');

    $before = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->json('lexemes');
    expect(collect($before)->firstWhere('id', $lexeme->id)['in_review'])->toBeFalse();

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    $after = $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")->json('lexemes');
    expect(collect($after)->firstWhere('id', $lexeme->id)['in_review'])->toBeTrue();
});

test('learned lexemes endpoint reports in_review after starting learning', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexeme] = createReadyContentWithLexeme('hello');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/mark-learned")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$lexeme->id}/start-learning")->assertOk();

    $data = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->json('data');

    expect(collect($data)->first()['in_review'])->toBeTrue();
    expect(collect($data)->first()['content_lexeme_id'])->toBe($lexeme->id);
});
