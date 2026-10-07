<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Learning\Application\LexemeConfidenceService;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;
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

test('the same canonical word from two contents has one card and two sources', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $first] = createReadyContentWithLexeme('shared');
    [, $second] = createReadyContentWithLexeme('shared');

    $this->actingAs($user)->postJson("/api/content/lexemes/{$first->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$second->id}/start-learning")->assertOk();

    expect($first->fresh()->lexeme_id)->toBe($second->fresh()->lexeme_id);
    expect(SrsCard::query()->where('user_id', $user->id)->count())->toBe(1);
    expect(UserLexemeSource::query()->where('user_id', $user->id)->where('lexeme_id', $first->fresh()->lexeme_id)->count())->toBe(2);
    expect(app(ReviewScheduleReaderInterface::class)->overdueContentIds($user->id))->toContain($first->content_id, $second->content_id);

    $confidence = app(LexemeConfidenceService::class);
    $confidence->record((int) $first->id, $user->id, ['recall' => 35]);
    $confidence->record((int) $second->id, $user->id, ['recognition' => 62]);
    expect(UserLexemeConfidence::query()->where('user_id', $user->id)->where('lexeme_id', $first->fresh()->lexeme_id)->count())->toBe(1);
    expect($confidence->get((int) $first->id, $user->id))->toMatchArray(['recall' => 35, 'recognition' => 62]);
});

test('deleting a source retains the card review and source snapshot', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [$content, $occurrence] = createReadyContentWithLexeme('retained');
    $this->actingAs($user)->postJson("/api/content/lexemes/{$occurrence->id}/start-learning")->assertOk();

    $card = SrsCard::query()->where('user_id', $user->id)->firstOrFail();
    $review = \App\Modules\Srs\Domain\Models\SrsReview::query()->create([
        'srs_card_id' => $card->id, 'grade' => 4, 'prev_interval' => 1, 'new_interval' => 3,
    ]);
    $source = UserLexemeSource::query()->where('user_id', $user->id)->firstOrFail();
    $sourceText = $source->source_text;

    $content->delete();

    expect($card->fresh()->content_id)->toBeNull()
        ->and($card->fresh()->lexeme_id)->toBe($occurrence->lexeme_id)
        ->and($review->fresh())->not->toBeNull()
        ->and($source->fresh()->content_lexeme_id)->toBeNull()
        ->and($source->fresh()->source_text)->toBe($sourceText);
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
