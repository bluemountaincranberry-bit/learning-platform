<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Learning\Application\ExerciseAttemptService;
use App\Modules\Learning\Application\LexemeConfidenceService;
use App\Modules\Learning\Domain\Models\LearningPointEvent;
use App\Modules\Learning\Interfaces\Jobs\ProcessExerciseAttemptJob;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('transcript attempts preserve explicit word outcomes and are idempotent when processing is retried', function (bool $linked, bool $scheduled) {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'phrase', 'text' => 'Could you help me?']);
    $segment = $content->transcriptSegments()->create([
        'sequence' => 0, 'start_ms' => 1000, 'end_ms' => 3000, 'text' => 'Could you help me?',
    ]);
    $card = $scheduled ? SrsCard::query()->create([
        'user_id' => $user->id, 'content_id' => $content->id, 'item_key' => 'phrase:Could you help me?',
        'state' => 'reviewing', 'interval_days' => 1, 'ease_factor' => 2.5, 'next_review_at' => now()->subMinute(),
    ]) : null;
    $confidence = app(LexemeConfidenceService::class);
    $beforeConfidence = $confidence->get($lexeme->id, $user->id);
    $payload = [
        'content_id' => $content->id, 'transcript_segment_id' => $segment->id,
        'exercise_type' => 'dictation', 'target_text' => 'Could you help me?', 'user_text' => 'Could you help me?',
    ];
    if ($linked) {
        $payload['content_lexeme_id'] = $lexeme->id;
    }
    $response = $this->actingAs($user)->postJson('/api/learning/exercise-attempts', $payload)->assertStatus(202);
    $attemptId = $response->json('attempt.id');
    $processor = app(ExerciseAttemptService::class);
    $processor->process($attemptId);
    $this->getJson("/api/learning/exercise-attempts/{$attemptId}")
        ->assertOk()->assertJsonPath('attempt.status', 'completed')->assertJsonPath('attempt.score', 100);
    $this->assertDatabaseHas('exercise_attempts', [
        'id' => $attemptId, 'transcript_segment_id' => $segment->id, 'content_lexeme_id' => $linked ? $lexeme->id : null,
    ]);
    $afterConfidence = $confidence->get($lexeme->id, $user->id);
    expect($afterConfidence['listening'])->toBe($linked ? 75 : $beforeConfidence['listening'])
        ->and(SrsReview::query()->count())->toBe($linked && $scheduled ? 1 : 0)
        ->and(LearningPointEvent::query()->count())->toBe($linked ? 1 : 0);
    if ($card) {
        expect($card->fresh()->next_review_at->isFuture())->toBe($linked);
    }
    $points = LearningPointEvent::query()->sum('points');
    $nextReview = $card?->fresh()->next_review_at->toISOString();
    $processor->process($attemptId);
    expect($confidence->get($lexeme->id, $user->id))->toBe($afterConfidence)
        ->and(SrsReview::query()->count())->toBe($linked && $scheduled ? 1 : 0)
        ->and(LearningPointEvent::query()->count())->toBe($linked ? 1 : 0)
        ->and(LearningPointEvent::query()->sum('points'))->toBe($points)
        ->and($card?->fresh()->next_review_at->toISOString())->toBe($nextReview);
    $this->assertDatabaseCount('exercise_attempts', 1);
    Queue::assertPushed(ProcessExerciseAttemptJob::class, 1);
})->with([
    'transcript only' => [false, false],
    'transcript only with unrelated scheduled word' => [false, true],
    'explicit word without scheduled review' => [true, false],
    'explicit scheduled word' => [true, true],
]);
