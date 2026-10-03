<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\TranscriptSegment;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Application\Data\ReviewOutcome;
use App\Modules\Srs\Domain\IntervalCalculator;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

test('srs due endpoint returns cards that are due', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'SRS Source',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:test',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonCount(1, 'items');
});

test('srs due endpoint returns lexeme_display for each card', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Due Lexeme Source',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'hello',
        'sort_order' => 0,
    ]);

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:hello',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $response->assertJsonPath('items.0.lexeme_display', 'hello');
});

test('srs due endpoint uses item_key fallback when no content_lexeme matches', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Orphan Card Source',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:orphan',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonPath('items.0.lexeme_display', 'orphan');
});

test('srs review updates card interval and creates review record', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'SRS Review Source',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:test2',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->postJson('/api/srs/review', [
        'card_id' => $card->id,
        'grade' => 5,
    ])->assertOk();

    $card->refresh();

    expect($card->interval_days)->toBeGreaterThanOrEqual(3);
    expect($card->reviews()->count())->toBe(1);
    expect((int) DB::table('learning_point_events')->where('user_id', $user->id)->sum('points'))->toBeGreaterThan(0);
});

test('srs review cannot access another users card', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    $content = Content::query()->create([
        'type' => 'book',
        'title' => 'Private SRS Source',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $owner->id,
        'content_id' => $content->id,
        'item_key' => 'word:private',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($attacker)->postJson('/api/srs/review', [
        'card_id' => $card->id,
        'grade' => 5,
    ])->assertNotFound();

    expect($card->fresh()->interval_days)->toBe(2);
    $this->assertDatabaseCount('srs_reviews', 0);
});

test('srs review rejects lexeme and transcript references from another content', function () {
    $user = User::factory()->create();
    $cardContent = Content::factory()->create(['status' => 'ready']);
    $otherContent = Content::factory()->create(['status' => 'ready']);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $cardContent->id,
        'item_key' => 'word:test',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);
    $lexeme = ContentLexeme::query()->create(['content_id' => $otherContent->id, 'type' => 'word', 'text' => 'other']);
    $segment = TranscriptSegment::query()->create([
        'content_id' => $otherContent->id,
        'sequence' => 1,
        'start_ms' => 0,
        'text' => 'Other content.',
    ]);

    foreach ([['content_lexeme_id' => $lexeme->id], ['transcript_segment_id' => $segment->id]] as $reference) {
        $this->actingAs($user)->postJson('/api/srs/review', [
            'card_id' => $card->id,
            'grade' => 5,
            ...$reference,
        ])->assertStatus(422);
    }

    expect($card->reviews()->count())->toBe(0);
});

test('current review API treats repeated requests as separate review answers', function () {
    // Characterization of the existing API. The refactor must replace this
    // expectation once an operation identifier is part of the public contract.
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:repeat',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $payload = ['card_id' => $card->id, 'grade' => 5];
    $this->actingAs($user)->postJson('/api/srs/review', $payload)->assertOk();
    $this->actingAs($user)->postJson('/api/srs/review', $payload)->assertOk();

    expect($card->reviews()->count())->toBe(2);
});

test('review rolls back card and history when required outcome handling fails', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:rollback',
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    app()->bind(ReviewOutcomeHandlerInterface::class, fn () => new class implements ReviewOutcomeHandlerInterface
    {
        public function handle(ReviewOutcome $outcome): void
        {
            throw new RuntimeException('Outcome handling failed');
        }
    });

    expect(fn () => app(SrsServiceInterface::class)->reviewCard(
        $card->id, 5, $user->id, app(IntervalCalculator::class),
    ))->toThrow(RuntimeException::class, 'Outcome handling failed');

    expect($card->fresh()->interval_days)->toBe(2)
        ->and($card->reviews()->count())->toBe(0);
});
