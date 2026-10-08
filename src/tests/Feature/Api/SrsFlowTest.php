<?php

use App\Modules\Content\Application\Contracts\SrsReviewReferenceReaderInterface;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\TranscriptSegment;
use App\Modules\Srs\Application\Contracts\ReviewScheduleReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewMistakesReaderInterface;
use App\Modules\Srs\Application\Contracts\ReviewOutcomeHandlerInterface;
use App\Modules\Srs\Application\Contracts\SrsServiceInterface;
use App\Modules\Srs\Application\Data\ReviewOutcome;
use App\Modules\Srs\Domain\IntervalCalculator;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function canonicalSrsCard(User $user, Content $content, string $lemma): array
{
    $lexeme = Lexeme::query()->create([
        'slug' => 'srs-'.str_replace(' ', '-', $lemma).'-'.uniqid(),
        'language' => 'en',
        'lemma' => $lemma,
        'normalized_lemma' => mb_strtolower($lemma),
        'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $occurrence = ContentLexeme::query()->create([
        'content_id' => $content->id,
        'type' => ContentLexeme::TYPE_WORD,
        'text' => $lemma,
        'lexeme_id' => $lexeme->id,
        'sort_order' => 1,
    ]);
    DB::table('user_lexeme_sources')->insert([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'source_kind' => 'content',
        'content_lexeme_id' => $occurrence->id,
        'lesson_lexeme_candidate_id' => null,
        'source_text' => $occurrence->text,
        'source_example' => null,
        'display_label_snapshot' => $content->title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_id' => $content->id,
        'item_key' => null,
        'state' => 'reviewing',
        'interval_days' => 2,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    return [$lexeme, $occurrence, $card];
}

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

    canonicalSrsCard($user, $content, 'test');

    $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonCount(1, 'items');
});

test('contentless personal SRS cards can be reviewed without legacy content lookup', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = Lexeme::query()->create([
        'slug' => 'personal-'.uniqid(), 'language' => 'en', 'lemma' => 'personal',
        'normalized_lemma' => 'personal', 'owner_user_id' => $user->id,
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'content_id' => null,
        'item_key' => null, 'state' => 'new', 'next_review_at' => now(),
    ]);

    $this->actingAs($user)->postJson('/api/srs/review', ['card_id' => $card->id, 'grade' => 4])
        ->assertOk();
    expect(SrsReview::query()->where('srs_card_id', $card->id)->value('content_lexeme_id'))->toBeNull();
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

    canonicalSrsCard($user, $content, 'hello');

    $response = $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonCount(1, 'items');

    $response->assertJsonPath('items.0.lexeme_display', 'hello');
});

test('srs due endpoint excludes cards without canonical identity', function () {
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
        ->assertJsonCount(0, 'items');
});

test('contentless canonical cards use lexeme identity in due and schedule readers', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = Lexeme::query()->create([
        'slug' => 'en-conversation',
        'language' => 'en',
        'lemma' => 'conversation',
        'normalized_lemma' => 'conversation',
        'status' => 'published',
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'content_id' => null,
        'item_key' => null,
        'state' => 'reviewing',
        'interval_days' => 4,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->getJson('/api/srs/due')
        ->assertOk()
        ->assertJsonPath('items.0.id', $card->id)
        ->assertJsonPath('items.0.lexeme_id', $lexeme->id)
        ->assertJsonPath('items.0.lexeme_display', 'conversation')
        ->assertJsonPath('items.0.content_id', null);

    $reader = app(ReviewScheduleReaderInterface::class);
    expect($reader->forUser($user->id, 10)['upcoming'][0]['item'])->toBe('conversation')
        ->and($reader->forUser($user->id, 10)['upcoming'][0]['lexeme_id'])->toBe($lexeme->id);
    expect($reader->wordsForQuiz($user->id, 10))->toBe(['conversation']);

    SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 1, 'prev_interval' => 4, 'new_interval' => 1, 'reviewed_at' => now()]);
    $mistake = app(ReviewMistakesReaderInterface::class)->recentForUser($user->id, 10)['mistakes'][0];
    expect($mistake['lexeme_id'])->toBe($lexeme->id);
    expect($mistake['item'])->toBe('conversation');
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

    [, $occurrence, $card] = canonicalSrsCard($user, $content, 'test2');

    $this->actingAs($user)->postJson('/api/srs/review', [
        'card_id' => $card->id,
        'grade' => 5,
    ])->assertOk();

    $card->refresh();

    expect($card->interval_days)->toBeGreaterThanOrEqual(3);
    expect($card->reviews()->count())->toBe(1);
    expect($card->reviews()->value('content_lexeme_id'))->toBe($occurrence->id);
    expect((int) DB::table('learning_point_events')->where('user_id', $user->id)->sum('points'))->toBeGreaterThan(0);
});

test('a review from another content occurrence is accepted for the canonical card', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('user');
    $contentA = Content::query()->create([
        'type' => 'book', 'title' => 'First source', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $contentB = Content::query()->create([
        'type' => 'book', 'title' => 'Second source', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    [$lexeme, , $card] = canonicalSrsCard($user, $contentA, 'shared-word');
    $secondOccurrence = ContentLexeme::query()->create([
        'content_id' => $contentB->id,
        'type' => ContentLexeme::TYPE_WORD,
        'text' => 'shared-word',
        'lexeme_id' => $lexeme->id,
        'sort_order' => 1,
    ]);
    DB::table('user_lexeme_sources')->insert([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->id,
        'source_kind' => 'content',
        'content_lexeme_id' => $secondOccurrence->id,
        'lesson_lexeme_candidate_id' => null,
        'source_text' => $secondOccurrence->text,
        'source_example' => null,
        'display_label_snapshot' => $contentB->title,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $queueItem = app(ReviewScheduleReaderInterface::class)->dueCards($user->id, $contentB->id)[0];
    expect($queueItem['content_id'])->toBe($contentB->id)
        ->and($queueItem['content_lexeme_id'])->toBe($secondOccurrence->id);
    expect(app(SrsReviewReferenceReaderInterface::class)
        ->contentLexemeIdForCard($user->id, $lexeme->id, null))->toBeNull();

    $this->actingAs($user)->postJson('/api/srs/review', [
        'card_id' => $card->id,
        'grade' => 5,
        'content_lexeme_id' => $secondOccurrence->id,
    ])->assertOk();

    expect((int) $card->reviews()->value('content_lexeme_id'))->toBe($secondOccurrence->id);
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
    [, $cardOccurrence, $card] = canonicalSrsCard($user, $cardContent, 'cardword');
    $otherLexeme = Lexeme::query()->create([
        'slug' => 'other-'.uniqid(), 'language' => 'en', 'lemma' => 'other',
        'normalized_lemma' => 'other', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $lexeme = ContentLexeme::query()->create([
        'content_id' => $otherContent->id, 'type' => 'word', 'text' => 'other', 'lexeme_id' => $otherLexeme->id,
    ]);
    $segment = TranscriptSegment::query()->create([
        'content_id' => $otherContent->id,
        'sequence' => 1,
        'start_ms' => 0,
        'text' => 'Other content.',
    ]);
    $segment->lexemes()->attach($lexeme->id, [
        'start_offset' => 0, 'end_offset' => 5, 'surface_text' => 'other', 'match_type' => 'exact', 'confidence' => 1.0,
    ]);
    $misassociatedSegment = TranscriptSegment::query()->create([
        'content_id' => $otherContent->id,
        'sequence' => 2,
        'start_ms' => 1000,
        'text' => 'A segment linked to a foreign occurrence.',
    ]);
    $misassociatedSegment->lexemes()->attach($cardOccurrence->id, [
        'start_offset' => 0, 'end_offset' => 4, 'surface_text' => 'card', 'match_type' => 'exact', 'confidence' => 1.0,
    ]);

    foreach ([['content_lexeme_id' => $lexeme->id], ['transcript_segment_id' => $segment->id], ['transcript_segment_id' => $misassociatedSegment->id]] as $reference) {
        $this->actingAs($user)->postJson('/api/srs/review', [
            'card_id' => $card->id,
            'grade' => 5,
            ...$reference,
        ])->assertStatus(422);
    }

    expect($card->reviews()->count())->toBe(0);
});

test('srs review rejects a same-content occurrence for a different canonical lexeme', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    [, $cardOccurrence, $card] = canonicalSrsCard($user, $content, 'first');
    $otherLexeme = Lexeme::query()->create([
        'slug' => 'second-'.uniqid(), 'language' => 'en', 'lemma' => 'second',
        'normalized_lemma' => 'second', 'status' => Lexeme::STATUS_PUBLISHED,
    ]);
    $otherOccurrence = ContentLexeme::query()->create([
        'content_id' => $content->id, 'type' => 'word', 'text' => 'second', 'lexeme_id' => $otherLexeme->id,
    ]);

    $this->actingAs($user)->postJson('/api/srs/review', [
        'card_id' => $card->id, 'grade' => 4, 'content_lexeme_id' => $otherOccurrence->id,
    ])->assertStatus(422);

    expect($card->reviews()->count())->toBe(0);
});

test('current review API treats repeated requests as separate review answers', function () {
    // Characterization of the existing API. The refactor must replace this
    // expectation once an operation identifier is part of the public contract.
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    [, , $card] = canonicalSrsCard($user, $content, 'repeat');

    $payload = ['card_id' => $card->id, 'grade' => 5];
    $this->actingAs($user)->postJson('/api/srs/review', $payload)->assertOk();
    $this->actingAs($user)->postJson('/api/srs/review', $payload)->assertOk();

    expect($card->reviews()->count())->toBe(2);
});

test('review rolls back card and history when required outcome handling fails', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    [, , $card] = canonicalSrsCard($user, $content, 'rollback');

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
