<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Application\Contracts\PersonalLexemeResolverInterface;
use App\Modules\Learning\Domain\Models\UserLexemeSource;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('my words requires auth', function () {
    $this->getJson('/api/me/words')
        ->assertUnauthorized();
});

test('personal contentless lexemes appear only in their owner\'s word list', function () {
    $owner = User::factory()->create();
    $owner->assignRole('user');
    $other = User::factory()->create();
    $other->assignRole('user');
    $lexeme = app(PersonalLexemeResolverInterface::class)->resolveOrCreate($owner->id, 'EN', 'retentive');

    $ownerResponse = $this->actingAs($owner)->getJson('/api/me/words')->assertOk();
    $otherResponse = $this->actingAs($other)->getJson('/api/me/words')->assertOk();

    expect($lexeme['is_personal'])->toBeTrue()
        ->and($ownerResponse->json('data'))->toHaveCount(1)
        ->and($ownerResponse->json('data.0.lexeme'))->toBe('retentive')
        ->and($ownerResponse->json('data.0.content_lexeme_id'))->toBeNull()
        ->and($otherResponse->json('data'))->toBeEmpty();
});

test('private-to-shared reconciliation preserves history and is exactly reversible', function () {
    $user = User::factory()->create();
    $private = app(PersonalLexemeResolverInterface::class)->resolveOrCreate($user->id, 'en', 'reconcile');
    $privateLexeme = Lexeme::query()->findOrFail($private['id']);
    $privateLexeme->update(['notes' => 'Keep this private note']);
    DB::table('lexeme_translations')->insert([
        'lexeme_id' => $privateLexeme->id, 'language' => 'ru', 'translation' => 'частное значение',
        'is_primary' => true, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $privateCard = SrsCard::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'content_id' => null, 'item_key' => null,
        'state' => 'reviewing', 'interval_days' => 5, 'ease_factor' => 2.4, 'next_review_at' => now()->addDays(5),
    ]);
    $privateReview = SrsReview::query()->create([
        'srs_card_id' => $privateCard->id, 'grade' => 4, 'prev_interval' => 3, 'new_interval' => 5, 'reviewed_at' => now()->subDays(2),
    ]);
    UserLexemeSource::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'source_kind' => 'manual',
        'source_text' => 'reconcile', 'display_label_snapshot' => 'My words',
    ]);
    $content = Content::query()->create(['type' => 'youtube', 'title' => 'Private source', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $occurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $content->id, 'type' => 'word', 'text' => 'reconcile', 'lexeme_id' => $privateLexeme->id,
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    UserLexemeSource::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'source_kind' => 'content',
        'content_lexeme_id' => $occurrenceId, 'source_text' => 'reconcile', 'display_label_snapshot' => 'Private source',
    ]);
    $privateProgressId = DB::table('user_lexeme_progress')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'content_lexeme_id' => $occurrenceId,
        'learned_at' => now()->subDays(5), 'created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5),
    ]);
    $privateProgress = DB::table('user_lexeme_progress')->where('lexeme_id', $privateLexeme->id)->first();

    $shared = Lexeme::query()->create([
        'slug' => 'reconcile-shared', 'language' => 'en', 'lemma' => 'reconcile', 'normalized_lemma' => 'reconcile', 'status' => 'published',
    ]);
    $sharedCard = SrsCard::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'content_id' => null, 'item_key' => null,
        'state' => 'learning', 'interval_days' => 8, 'ease_factor' => 2.1, 'next_review_at' => now()->addDays(8),
        'updated_at' => now()->addSecond(),
    ]);
    $sharedReview = SrsReview::query()->create([
        'srs_card_id' => $sharedCard->id, 'grade' => 2, 'prev_interval' => 3, 'new_interval' => 8, 'reviewed_at' => now()->subDay(),
    ]);
    UserLexemeSource::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'source_kind' => 'manual',
        'source_text' => 'reconcile', 'display_label_snapshot' => 'My words',
    ]);
    $content2 = Content::query()->create(['type' => 'youtube', 'title' => 'Shared source', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready']);
    $sharedOccurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $content2->id, 'type' => 'word', 'text' => 'reconcile', 'lexeme_id' => $shared->id,
        'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    UserLexemeSource::query()->create([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'source_kind' => 'content',
        'content_lexeme_id' => $sharedOccurrenceId, 'source_text' => 'reconcile', 'display_label_snapshot' => 'Shared source',
    ]);
    $sharedProgressId = DB::table('user_lexeme_progress')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'content_lexeme_id' => $sharedOccurrenceId,
        'learned_at' => now()->subDay(), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('user_lexeme_confidences')->insert([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'content_lexeme_id' => $occurrenceId,
        'recognition' => 20, 'recall' => 21, 'production' => 22, 'listening' => 23, 'speaking' => 24,
        'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
    ]);
    $winnerConfidenceId = DB::table('user_lexeme_confidences')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'content_lexeme_id' => $sharedOccurrenceId,
        'recognition' => 90, 'recall' => 91, 'production' => 92, 'listening' => 93, 'speaking' => 94,
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('user_lexeme_skips')->insert([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
    ]);
    $winnerSkipId = DB::table('user_lexeme_skips')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('user_lexeme_context_checks')->insert([
        'user_id' => $user->id, 'lexeme_id' => $privateLexeme->id, 'last_result' => 'needs_work',
        'checked_at' => now()->subDays(2), 'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
    ]);
    $winnerContextId = DB::table('user_lexeme_context_checks')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $shared->id, 'last_result' => 'correct',
        'checked_at' => now()->subDay(), 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    $progressBefore = DB::table('user_lexeme_progress')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    $learningBefore = [];
    foreach (['user_lexeme_confidences', 'user_lexeme_skips', 'user_lexeme_context_checks'] as $table) {
        $learningBefore[$table] = DB::table($table)->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    $resolved = app(PersonalLexemeResolverInterface::class)->resolveOrCreate($user->id, 'en', 'reconcile');
    $auditId = DB::table('personal_lexeme_reconciliation_audits')->value('id');

    expect($resolved['id'])->toBe((int) $shared->id)
        ->and((int) $privateLexeme->fresh()->alias_to_lexeme_id)->toBe((int) $shared->id)
        ->and(DB::table('srs_cards')->where('user_id', $user->id)->where('lexeme_id', $shared->id)->count())->toBe(1)
        ->and(DB::table('srs_cards')->where('id', $privateCard->id)->value('interval_days'))->toBe(8)
        ->and(DB::table('srs_reviews')->whereIn('id', [$privateReview->id, $sharedReview->id])->count())->toBe(2)
        ->and(DB::table('srs_reviews')->whereIn('id', [$privateReview->id, $sharedReview->id])->where('srs_card_id', $privateCard->id)->count())->toBe(2)
        ->and(DB::table('user_lexeme_sources')->where('user_id', $user->id)->where('lexeme_id', $shared->id)->count())->toBe(3)
        ->and(DB::table('user_lexeme_progress')->where('user_id', $user->id)->count())->toBe(1)
        ->and((int) DB::table('user_lexeme_progress')->where('user_id', $user->id)->value('id'))->toBe($sharedProgressId)
        ->and((int) DB::table('user_lexeme_confidences')->where('user_id', $user->id)->value('id'))->toBe($winnerConfidenceId)
        ->and(DB::table('user_lexeme_confidences')->where('user_id', $user->id)->value('recognition'))->toBe(90)
        ->and((int) DB::table('user_lexeme_skips')->where('user_id', $user->id)->value('id'))->toBe($winnerSkipId)
        ->and((int) DB::table('user_lexeme_context_checks')->where('user_id', $user->id)->value('id'))->toBe($winnerContextId)
        ->and(DB::table('user_lexeme_context_checks')->where('user_id', $user->id)->value('last_result'))->toBe('correct');
    expect(DB::table('user_lexeme_sources')->where('user_id', $user->id)->where('lexeme_id', $shared->id)->where('source_kind', 'manual')->value('source_example'))
        ->toContain('Keep this private note', 'частное значение');

    app(\App\Modules\Learning\Application\PersonalLexemeReconciler::class)->rollback((int) $auditId);

    expect($privateLexeme->fresh()->alias_to_lexeme_id)->toBeNull()
        ->and(DB::table('srs_cards')->where('user_id', $user->id)->whereIn('lexeme_id', [$privateLexeme->id, $shared->id])->count())->toBe(2)
        ->and(DB::table('srs_reviews')->whereIn('id', [$privateReview->id, $sharedReview->id])->pluck('srs_card_id')->all())->toBe([$privateCard->id, $sharedCard->id])
        ->and(DB::table('user_lexeme_progress')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($progressBefore)
        ->and(DB::table('user_lexeme_confidences')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($learningBefore['user_lexeme_confidences'])
        ->and(DB::table('user_lexeme_skips')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($learningBefore['user_lexeme_skips'])
        ->and(DB::table('user_lexeme_context_checks')->where('user_id', $user->id)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($learningBefore['user_lexeme_context_checks'])
        ->and(DB::table('user_lexeme_sources')->where('user_id', $user->id)->whereIn('lexeme_id', [$privateLexeme->id, $shared->id])->count())->toBe(4)
        ->and(DB::table('personal_lexeme_reconciliation_audits')->where('id', $auditId)->value('status'))->toBe('rolled_back');
});

test('personal word identity keeps equal spelling separate across languages', function () {
    $user = User::factory()->create();
    $english = app(PersonalLexemeResolverInterface::class)->resolveOrCreate($user->id, 'EN', 'gift');
    $german = app(PersonalLexemeResolverInterface::class)->resolveOrCreate($user->id, 'de', 'gift');

    expect($english['id'])->not->toBe($german['id'])
        ->and($english['language'])->toBe('en')
        ->and($german['language'])->toBe('de');
});


test('adding a personal word creates one contentless source and one canonical card', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $payload = ['lemma' => 'retain', 'language' => 'en'];
    $this->actingAs($user)->postJson('/api/me/words', $payload)
        ->assertCreated()
        ->assertJsonPath('lexeme.lemma', 'retain')
        ->assertJsonPath('lexeme.is_personal', true);
    $this->actingAs($user)->postJson('/api/me/words', $payload)->assertCreated();

    $card = SrsCard::query()->where('user_id', $user->id)->firstOrFail();
    expect($card->lexeme_id)->not->toBeNull()
        ->and($card->content_id)->toBeNull()
        ->and($card->item_key)->toBeNull()
        ->and(SrsCard::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(UserLexemeSource::query()->where('user_id', $user->id)->where('source_kind', 'manual')->count())->toBe(1);
});

test('contentless word recall updates canonical confidence and SRS history', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $other = User::factory()->create();
    $other->assignRole('user');
    $lexemeId = $this->actingAs($user)->postJson('/api/me/words', ['lemma' => 'sonder', 'language' => 'en'])
        ->assertCreated()->json('lexeme.id');

    $this->actingAs($other)->postJson("/api/me/words/{$lexemeId}/practice", [
        'dimension' => 'recall', 'correct' => false,
    ])->assertNotFound();
    $this->actingAs($user)->postJson("/api/me/words/{$lexemeId}/practice", [
        'dimension' => 'recall', 'correct' => false,
    ])->assertOk()->assertJsonPath('review_scheduled', true);

    $card = SrsCard::query()->where('user_id', $user->id)->where('lexeme_id', $lexemeId)->firstOrFail();
    $review = SrsReview::query()->where('srs_card_id', $card->id)->firstOrFail();
    $confidence = \App\Modules\Learning\Domain\Models\UserLexemeConfidence::query()
        ->where('user_id', $user->id)->where('lexeme_id', $lexemeId)->firstOrFail();

    expect($confidence->content_lexeme_id)->toBeNull()
        ->and($confidence->recall)->toBe(20)
        ->and($review->content_lexeme_id)->toBeNull()
        ->and($review->exercise_type)->toBe('self_check')
        ->and($card->state)->toBe('relearning');
});

test('personal words stop and restart without losing card schedule or reviews', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $response = $this->actingAs($user)->postJson('/api/me/words', ['lemma' => 'persist', 'language' => 'en'])->assertCreated();
    $lexemeId = $response->json('lexeme.id');
    $card = SrsCard::query()->where('user_id', $user->id)->firstOrFail();
    $nextReviewAt = now()->addDays(6)->startOfSecond();
    $card->update(['state' => 'reviewing', 'interval_days' => 6, 'next_review_at' => $nextReviewAt]);
    $review = SrsReview::query()->create(['srs_card_id' => $card->id, 'grade' => 4, 'prev_interval' => 3, 'new_interval' => 6]);

    $this->actingAs($user)->postJson("/api/me/words/{$lexemeId}/stop-learning")->assertOk();
    expect($card->fresh()->deactivated_at)->not->toBeNull();
    $this->actingAs($user)->postJson("/api/me/words/{$lexemeId}/start-learning")->assertOk();

    $card->refresh();
    expect($card->deactivated_at)->toBeNull()
        ->and($card->interval_days)->toBe(6)
        ->and($card->next_review_at->equalTo($nextReviewAt))->toBeTrue()
        ->and(SrsReview::query()->whereKey($review->id)->exists())->toBeTrue();
});

test('personal contentless words can be marked known and appear in learned words', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    $other = User::factory()->create();
    $other->assignRole('user');
    $response = $this->actingAs($user)->postJson('/api/me/words', ['lemma' => 'fluent', 'language' => 'en'])->assertCreated();
    $lexemeId = $response->json('lexeme.id');

    $this->actingAs($user)->postJson("/api/me/words/{$lexemeId}/mark-known")->assertOk();
    $learned = $this->actingAs($user)->getJson('/api/me/learned-lexemes')->assertOk();
    expect($learned->json('data.0.lexeme'))->toBe('fluent')
        ->and($learned->json('data.0.content_lexeme_id'))->toBeNull();

    $this->actingAs($other)->postJson("/api/me/words/{$lexemeId}/mark-known")->assertNotFound();
    $this->actingAs($user)->deleteJson("/api/me/words/{$lexemeId}/mark-known")->assertOk();
    expect($this->actingAs($user)->getJson('/api/me/learned-lexemes')->json('data'))->toBeEmpty();
});

test('my words shows words in learning before known words', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Demo',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $learning = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $known = $content->lexemes()->create(['type' => 'word', 'text' => 'walk', 'sort_order' => 2]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$learning->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$known->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/me/words')->assertOk();

    expect($response->json('data.0.lexeme'))->toBe('run');
    expect($response->json('data.0.status'))->toBe('in_learning');
    expect($response->json('data.1.lexeme'))->toBe('walk');
    expect($response->json('data.1.status'))->toBe('known');
});

test('my words filters by status content and level', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $contentA = Content::query()->create([
        'type' => 'youtube',
        'title' => 'A',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $contentB = Content::query()->create([
        'type' => 'youtube',
        'title' => 'B',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $a1 = $contentA->lexemes()->create(['type' => 'word', 'text' => 'apple', 'sort_order' => 1]);
    $b1 = $contentB->lexemes()->create(['type' => 'word', 'text' => 'banana', 'sort_order' => 1]);

    $a1->canonicalLexeme()->update(['level' => 'A1']);
    $b1->canonicalLexeme()->update(['level' => 'B1']);

    $response = $this->actingAs($user)
        ->getJson("/api/me/words?status=new&content_id={$contentA->id}&level=A1")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.lexeme'))->toBe('apple');
    expect($response->json('data.0.status'))->toBe('new');
    expect($response->json('data.0.level'))->toBe('A1');
    expect($response->json('data.0.contexts.0.content_id'))->toBe($contentA->id);
});

test('my words can return only words in learning', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Demo',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $learning = $content->lexemes()->create(['type' => 'word', 'text' => 'focus', 'sort_order' => 1]);
    $known = $content->lexemes()->create(['type' => 'word', 'text' => 'easy', 'sort_order' => 2]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$learning->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$known->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/me/words?status=in_learning')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.lexeme'))->toBe('focus');
    expect($response->json('data.0.in_review'))->toBeTrue();
});
