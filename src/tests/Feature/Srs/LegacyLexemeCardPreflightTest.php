<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Srs\Application\LegacyLexemeCardPreflight;
use App\Modules\Srs\Application\LegacySrsCardMigration;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function legacyCardFixture(string $text = 'run', string $language = 'en', ?int $lexemeId = null): array
{
    $user = User::factory()->create();
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Legacy card fixture',
        'language' => $language,
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexeme = $lexemeId === null ? Lexeme::query()->create([
        'slug' => $language.'-'.str_replace(' ', '-', $text).'-'.uniqid(),
        'language' => $language,
        'lemma' => $text,
        'normalized_lemma' => mb_strtolower($text),
        'status' => 'published',
    ]) : Lexeme::query()->findOrFail($lexemeId);
    $occurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => $text,
        'lexeme_id' => $lexeme->id,
        'sort_order' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:'.$text,
        'state' => 'learning',
        'interval_days' => 3,
        'ease_factor' => 2.3,
        'next_review_at' => now()->addDays(2),
    ]);

    return [$user, $content, $lexeme, $occurrenceId, $card];
}

test('preflight resolves a unique canonical occurrence and does not mutate learner rows', function () {
    [, , , $occurrenceId, $card] = legacyCardFixture();
    $review = SrsReview::query()->create([
        'srs_card_id' => $card->id,
        'content_lexeme_id' => $occurrenceId,
        'grade' => 3,
        'prev_interval' => 1,
        'new_interval' => 3,
        'reviewed_at' => now()->subDay(),
    ]);
    $beforeCard = (array) DB::table('srs_cards')->where('id', $card->id)->first();
    $beforeReview = (array) DB::table('srs_reviews')->where('id', $review->id)->first();

    $this->artisan('srs:preflight-lexeme-cards --json')->assertExitCode(0);

    expect((array) DB::table('srs_cards')->where('id', $card->id)->first())->toEqual($beforeCard)
        ->and((array) DB::table('srs_reviews')->where('id', $review->id)->first())->toEqual($beforeReview);

    $report = app(LegacyLexemeCardPreflight::class)->report();
    expect($report['resolved'])->toBe(1)
        ->and($report['collision_audit'][0]['same_key_occurrences'][0]['content_lexeme_id'])->toBe($occurrenceId);
});

test('user source kind cannot carry a reference of another source kind', function () {
    [$user, , $lexeme, $occurrenceId] = legacyCardFixture();

    expect(fn () => DB::table('user_lexeme_sources')->insert([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'source_kind' => 'manual',
        'content_lexeme_id' => $occurrenceId, 'lesson_lexeme_candidate_id' => null,
        'source_text' => 'run', 'source_example' => null, 'display_label_snapshot' => 'My words',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('preflight blocks duplicate source occurrences even when both point to the same lexeme', function () {
    [, $content, $lexeme] = legacyCardFixture();
    DB::table('content_lexemes')->insert([
        'content_id' => $content->id,
        'type' => 'word',
        'text' => 'run',
        'lexeme_id' => $lexeme->id,
        'sort_order' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('srs:preflight-lexeme-cards --json')->expectsOutputToContain('ambiguous_source_occurrence')->assertExitCode(1);
});

test('preflight blocks a card when a saved review points at another lexeme', function () {
    [, , $firstLexeme, $occurrenceId, $card] = legacyCardFixture();
    $otherLexeme = Lexeme::query()->create([
        'slug' => 'other-'.uniqid(),
        'language' => 'en',
        'lemma' => 'sprint',
        'normalized_lemma' => 'sprint',
        'status' => 'published',
    ]);
    $otherContent = Content::query()->create([
        'type' => 'youtube', 'title' => 'Other context', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready',
    ]);
    $otherOccurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $otherContent->id, 'type' => 'word', 'text' => 'sprint',
        'lexeme_id' => $otherLexeme->id, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    SrsReview::query()->create([
        'srs_card_id' => $card->id, 'content_lexeme_id' => $otherOccurrenceId,
        'grade' => 2, 'prev_interval' => 1, 'new_interval' => 1, 'reviewed_at' => now(),
    ]);

    expect($firstLexeme->id)->not->toBe($otherLexeme->id);
    $this->artisan('srs:preflight-lexeme-cards --json')->expectsOutputToContain('review_context_conflicts_with_card')->assertExitCode(1);
});

test('preflight blocks a canonical lexeme whose language differs from its source', function () {
    [, $content, $lexeme] = legacyCardFixture();
    DB::table('lexemes')->where('id', $lexeme->id)->update(['language' => 'fr']);
    DB::table('contents')->where('id', $content->id)->update(['language' => 'en']);

    $this->artisan('srs:preflight-lexeme-cards --json')->expectsOutputToContain('unknown_or_mismatched_language')->assertExitCode(1);
});

test('collision audit includes learning flags, confidence, and legacy answers for matching occurrences', function () {
    [$user, $content, $lexeme, $occurrenceId] = legacyCardFixture();
    DB::table('user_lexeme_progress')->insert([
        'user_id' => $user->id,
        'content_lexeme_id' => $occurrenceId,
        'lexeme_id' => $lexeme->id,
        'learned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('user_lexeme_confidences')->insert([
        'user_id' => $user->id,
        'content_lexeme_id' => $occurrenceId,
        'recognition' => 3,
        'recall' => 2,
        'production' => 1,
        'listening' => 0,
        'speaking' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $sessionId = DB::table('learning_sessions')->insertGetId([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'status' => 'completed',
        'started_at' => now()->subMinute(),
        'completed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $answerId = DB::table('learning_answers')->insertGetId([
        'learning_session_id' => $sessionId,
        'item_key' => 'word:run',
        'result' => 'correct',
        'score' => 4,
        'answered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $audit = app(LegacyLexemeCardPreflight::class)->report()['collision_audit'][0]['learning_references'];

    expect($audit['progress'][0]['content_lexeme_id'])->toBe($occurrenceId)
        ->and($audit['confidence'][0]['content_lexeme_id'])->toBe($occurrenceId)
        ->and($audit['answers'][0]['id'])->toBe($answerId);
});

test('preflight maps progress provenance by occurrence and blocks language mismatches', function () {
    [$user, , , $occurrenceId] = legacyCardFixture();
    DB::table('user_lexeme_progress')->insert([
        'user_id' => $user->id, 'content_lexeme_id' => $occurrenceId,
        'lexeme_id' => DB::table('content_lexemes')->where('id', $occurrenceId)->value('lexeme_id'),
        'learned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $report = app(LegacyLexemeCardPreflight::class)->report();
    expect($report['resolved_progress_mappings'])->toHaveCount(1)
        ->and($report['resolved_progress_mappings'][0]['content_lexeme_id'])->toBe($occurrenceId);

    DB::table('contents')->where('id', DB::table('content_lexemes')->where('id', $occurrenceId)->value('content_id'))->update(['language' => 'fr']);
    $report = app(LegacyLexemeCardPreflight::class)->report();
    expect(collect($report['unresolved'])->firstWhere('record_type', 'progress')['reason'])->toBe('progress_source_language_mismatch');
});

test('migration audit snapshots learner flags attempts and all answers in affected content', function () {
    [$user, $content, $lexeme, $occurrenceId] = legacyCardFixture();
    $skipId = DB::table('user_lexeme_skips')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $checkId = DB::table('user_lexeme_context_checks')->insertGetId([
        'user_id' => $user->id, 'lexeme_id' => $lexeme->id, 'last_result' => 'needs_work',
        'checked_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $attemptId = DB::table('exercise_attempts')->insertGetId([
        'user_id' => $user->id, 'content_id' => $content->id, 'content_lexeme_id' => $occurrenceId,
        'exercise_type' => 'dictation', 'status' => 'completed', 'target_text' => 'run',
        'user_text' => 'ran', 'is_correct' => false, 'hint_used' => false, 'replay_count' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $sessionId = DB::table('learning_sessions')->insertGetId([
        'user_id' => $user->id, 'content_id' => $content->id, 'status' => 'completed',
        'started_at' => now()->subMinute(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $answerId = DB::table('learning_answers')->insertGetId([
        'learning_session_id' => $sessionId, 'item_key' => 'word:unmapped-answer', 'result' => 'correct',
        'score' => 4, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $unmappedLearner = User::factory()->create();
    $unmappedAttemptId = DB::table('exercise_attempts')->insertGetId([
        'user_id' => $unmappedLearner->id, 'content_id' => $content->id, 'content_lexeme_id' => null,
        'exercise_type' => 'dictation', 'status' => 'completed', 'target_text' => 'unmapped',
        'user_text' => 'unmapped', 'is_correct' => true, 'hint_used' => false, 'replay_count' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $unmappedSessionId = DB::table('learning_sessions')->insertGetId([
        'user_id' => $unmappedLearner->id, 'content_id' => $content->id, 'status' => 'completed',
        'started_at' => now()->subMinute(), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $unmappedAnswerId = DB::table('learning_answers')->insertGetId([
        'learning_session_id' => $unmappedSessionId, 'item_key' => 'legacy:orphan', 'result' => 'wrong',
        'score' => 0, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $auditId = app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy');
    $payload = json_decode(DB::table('srs_legacy_migration_audits')->where('id', $auditId)->value('payload'), true, flags: JSON_THROW_ON_ERROR);
    $before = $payload['related_learning_before'];

    expect($before['counts']['user_lexeme_skips'])->toBe(1)
        ->and($before['primary_keys']['user_lexeme_skips'])->toBe([$skipId])
        ->and($before['primary_keys']['user_lexeme_context_checks'])->toBe([$checkId])
        ->and($before['primary_keys']['exercise_attempts'])->toBe([$attemptId, $unmappedAttemptId])
        ->and($before['primary_keys']['learning_answers'])->toBe([$answerId, $unmappedAnswerId])
        ->and($before['rows']['learning_answers'][0]['item_key'])->toBe('word:unmapped-answer')
        ->and($before['rows']['learning_answers'][1]['item_key'])->toBe('legacy:orphan');
});

test('card cutover aborts before mutation when preflight has unresolved legacy identity', function () {
    [, $content, , , $card] = legacyCardFixture();
    DB::table('srs_cards')->where('id', $card->id)->update(['item_key' => 'unknown-format']);
    $before = (array) DB::table('srs_cards')->where('id', $card->id)->first();

    expect(fn () => app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy'))
        ->toThrow(RuntimeException::class, 'preflight has unresolved cards');

    expect((array) DB::table('srs_cards')->where('id', $card->id)->first())->toEqual($before)
        ->and(DB::table('srs_legacy_migration_audits')->count())->toBe(0)
        ->and(DB::table('user_lexeme_sources')->count())->toBe(0);
});

test('card cutover aborts without changing a confidence row that has no canonical source', function () {
    [$user, , , $occurrenceId] = legacyCardFixture();
    $confidenceId = DB::table('user_lexeme_confidences')->insertGetId([
        'user_id' => $user->id, 'content_lexeme_id' => $occurrenceId,
        'recognition' => 30, 'recall' => 20, 'production' => 10, 'listening' => 0, 'speaking' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('content_lexemes')->where('id', $occurrenceId)->update(['lexeme_id' => null]);
    $before = (array) DB::table('user_lexeme_confidences')->where('id', $confidenceId)->first();

    expect(fn () => app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy'))
        ->toThrow(RuntimeException::class, 'preflight has unresolved cards');

    expect((array) DB::table('user_lexeme_confidences')->where('id', $confidenceId)->first())->toEqual($before)
        ->and(DB::table('srs_legacy_migration_audits')->count())->toBe(0);
});

test('card cutover merges duplicate canonical schedules and keeps all review IDs and source encounters', function () {
    [$user, , $lexeme, $firstOccurrenceId, $firstCard] = legacyCardFixture();
    SrsReview::query()->create([
        'srs_card_id' => $firstCard->id,
        'content_lexeme_id' => $firstOccurrenceId,
        'grade' => 2,
        'prev_interval' => 1,
        'new_interval' => 3,
        'reviewed_at' => now()->subDays(3),
    ]);
    DB::table('user_lexeme_confidences')->insert([
        'user_id' => $user->id, 'content_lexeme_id' => $firstOccurrenceId,
        'recognition' => 10, 'recall' => 11, 'production' => 12, 'listening' => 13, 'speaking' => 14,
        'created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2),
    ]);
    $secondContent = Content::query()->create([
        'type' => 'youtube', 'title' => 'Second source', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready',
    ]);
    $secondOccurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $secondContent->id, 'type' => 'word', 'text' => 'ran',
        'lexeme_id' => $lexeme->id, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $secondConfidenceId = DB::table('user_lexeme_confidences')->insertGetId([
        'user_id' => $user->id, 'content_lexeme_id' => $secondOccurrenceId,
        'recognition' => 30, 'recall' => 31, 'production' => 32, 'listening' => 33, 'speaking' => 34,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $secondCardId = DB::table('srs_cards')->insertGetId([
        'user_id' => $user->id, 'content_id' => $secondContent->id, 'item_key' => 'word:ran',
        'state' => 'relearning', 'interval_days' => 7, 'ease_factor' => 2.1,
        'next_review_at' => now()->addDays(7), 'created_at' => now()->subMinute(), 'updated_at' => now(),
    ]);
    $secondReviewId = DB::table('srs_reviews')->insertGetId([
        'srs_card_id' => $secondCardId, 'content_lexeme_id' => $secondOccurrenceId,
        'grade' => 4, 'prev_interval' => 3, 'new_interval' => 7, 'reviewed_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $progressOnlyContent = Content::query()->create([
        'type' => 'youtube', 'title' => 'Learned-only source', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready',
    ]);
    $progressOccurrenceId = DB::table('content_lexemes')->insertGetId([
        'content_id' => $progressOnlyContent->id, 'type' => 'word', 'text' => 'run',
        'lexeme_id' => $lexeme->id, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $progressId = DB::table('user_lexeme_progress')->insertGetId([
        'user_id' => $user->id, 'content_lexeme_id' => $progressOccurrenceId, 'lexeme_id' => $lexeme->id,
        'learned_at' => now()->subDays(10), 'created_at' => now()->subDays(10), 'updated_at' => now()->subDays(10),
    ]);
    $confidenceBefore = DB::table('user_lexeme_confidences')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    $progressBefore = DB::table('user_lexeme_progress')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();

    $auditId = app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy');
    $survivor = DB::table('srs_cards')->where('id', $firstCard->id)->first();

    expect(DB::table('srs_cards')->where('user_id', $user->id)->count())->toBe(1)
        ->and((int) $survivor->lexeme_id)->toBe($lexeme->id)
        ->and($survivor->state)->toBe('relearning')
        ->and((int) $survivor->interval_days)->toBe(7)
        ->and((float) $survivor->ease_factor)->toBe(2.1)
        ->and(Carbon\Carbon::parse($survivor->next_review_at)->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and(DB::table('srs_reviews')->count())->toBe(2)
        ->and(DB::table('srs_reviews')->where('id', $secondReviewId)->value('srs_card_id'))->toBe($firstCard->id)
        ->and(DB::table('user_lexeme_confidences')->count())->toBe(1)
        ->and(DB::table('user_lexeme_confidences')->value('id'))->toBe($secondConfidenceId)
        ->and(DB::table('user_lexeme_confidences')->value('lexeme_id'))->toBe($lexeme->id)
        ->and(DB::table('user_lexeme_confidences')->value('recognition'))->toBe(30)
        ->and(DB::table('user_lexeme_sources')->where('user_id', $user->id)->count())->toBe(3)
        ->and(DB::table('user_lexeme_sources')->where('user_id', $user->id)->where('content_lexeme_id', $progressOccurrenceId)->value('source_kind'))->toBe('content')
        ->and(data_get(json_decode(DB::table('srs_legacy_migration_audits')->where('id', $auditId)->value('payload'), true), 'progress_source_mappings.0.progress_id'))->toBe($progressId)
        ->and(DB::table('srs_legacy_migration_audits')->where('id', $auditId)->value('status'))->toBe('applied');

    app(LegacySrsCardMigration::class)->rollback($auditId);

    expect(DB::table('srs_cards')->where('id', $secondCardId)->exists())->toBeTrue()
        ->and(DB::table('srs_cards')->whereNull('lexeme_id')->count())->toBe(2)
        ->and(DB::table('srs_reviews')->where('id', $secondReviewId)->value('srs_card_id'))->toBe($secondCardId)
        ->and(DB::table('user_lexeme_confidences')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($confidenceBefore)
        ->and(DB::table('user_lexeme_progress')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all())->toEqual($progressBefore)
        ->and(DB::table('user_lexeme_sources')->count())->toBe(0)
        ->and(DB::table('srs_legacy_migration_audits')->where('id', $auditId)->value('status'))->toBe('rolled_back');
});

test('cutover rollback refuses to overwrite review activity recorded after apply', function () {
    [, , , $occurrenceId, $card] = legacyCardFixture();
    $auditId = app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy');
    DB::table('srs_reviews')->insert([
        'srs_card_id' => $card->id, 'content_lexeme_id' => $occurrenceId,
        'grade' => 5, 'prev_interval' => 1, 'new_interval' => 3, 'reviewed_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(LegacySrsCardMigration::class)->rollback($auditId))
        ->toThrow(RuntimeException::class, 'changed after cutover');

    expect(DB::table('srs_cards')->where('id', $card->id)->value('lexeme_id'))->not->toBeNull()
        ->and(DB::table('srs_reviews')->count())->toBe(1)
        ->and(DB::table('srs_legacy_migration_audits')->where('id', $auditId)->value('status'))->toBe('applied');
});

test('re-running an unchanged cutover is a no-op and returns its original audit ID', function () {
    app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy');
    $firstAuditId = DB::table('srs_legacy_migration_audits')->value('id');

    $secondAuditId = app(LegacySrsCardMigration::class)->apply('test-fixture:verified-copy');

    expect($secondAuditId)->toBe($firstAuditId)
        ->and(DB::table('srs_legacy_migration_audits')->count())->toBe(1);
});

test('migration command defaults to a read-only preview and requires an explicit write-pause confirmation', function () {
    [, , , , $card] = legacyCardFixture();
    $before = (array) DB::table('srs_cards')->where('id', $card->id)->first();

    $this->artisan('srs:migrate-legacy-cards')->assertExitCode(0);
    $this->artisan('srs:migrate-legacy-cards --apply --verified-backup=test-copy')
        ->expectsOutputToContain('--writes-paused')
        ->assertExitCode(1);

    expect((array) DB::table('srs_cards')->where('id', $card->id)->first())->toEqual($before)
        ->and(DB::table('srs_legacy_migration_audits')->count())->toBe(0);
});
