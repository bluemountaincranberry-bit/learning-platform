<?php

namespace Tests\Feature\Api;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use App\Modules\Learning\Application\Contracts\PronunciationAssessmentProviderInterface;
use App\Modules\Learning\Application\Contracts\SpeechToTextProviderInterface;
use App\Modules\Learning\Interfaces\Jobs\ProcessExerciseAttemptJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('dictation attempt compares text and links transcript context', function () {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'phrase', 'text' => 'Could you help me?']);
    $segment = $content->transcriptSegments()->create(['sequence' => 0, 'start_ms' => 1000, 'end_ms' => 3000, 'text' => 'Could you help me?']);

    $response = $this->actingAs($user)->postJson('/api/learning/exercise-attempts', [
        'content_id' => $content->id, 'content_lexeme_id' => $lexeme->id, 'transcript_segment_id' => $segment->id,
        'exercise_type' => 'dictation', 'target_text' => 'Could you help me?', 'user_text' => 'could you help me',
        'hint_used' => false, 'replay_count' => 1,
    ]);

    $response->assertStatus(202)->assertJsonPath('attempt.status', 'pending');
    Queue::assertPushed(ProcessExerciseAttemptJob::class);
    $job = Queue::pushed(ProcessExerciseAttemptJob::class)->first();
    app(\App\Modules\Learning\Application\ExerciseAttemptService::class)->process($job->attemptId);
    expect(\App\Modules\Learning\Domain\Models\ExerciseAttempt::query()->latest('id')->value('score'))->toBe(100);
    $this->assertDatabaseHas('exercise_attempts', ['content_lexeme_id' => $lexeme->id, 'transcript_segment_id' => $segment->id, 'exercise_type' => 'dictation']);
});

test('exercise attempts cannot be created for non-public content', function () {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'draft']);

    $this->actingAs($user)->postJson('/api/learning/exercise-attempts', [
        'content_id' => $content->id,
        'exercise_type' => 'dictation',
        'target_text' => 'hidden lesson',
        'user_text' => 'hidden lesson',
    ])->assertNotFound();

    Queue::assertNothingPushed();
    $this->assertDatabaseCount('exercise_attempts', 0);
});

test('shadowing attempt uses speech and pronunciation provider contracts', function () {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);
    app()->instance(SpeechToTextProviderInterface::class, new class implements SpeechToTextProviderInterface
    {
        public function transcribe(string $audioPath, string $language): array
        {
            return ['text' => 'hello', 'confidence' => 0.95, 'provider' => 'fake'];
        }
    });
    app()->instance(PronunciationAssessmentProviderInterface::class, new class implements PronunciationAssessmentProviderInterface
    {
        public function assess(string $audioPath, string $targetText, string $language): array
        {
            return ['accuracy' => 88, 'fluency' => 80, 'completeness' => 100, 'prosody' => 75, 'words' => [], 'provider' => 'fake'];
        }
    });

    $response = $this->actingAs($user)->post('/api/learning/exercise-attempts', [
        'content_id' => $content->id, 'content_lexeme_id' => $lexeme->id, 'exercise_type' => 'shadowing',
        'target_text' => 'hello', 'audio' => \Illuminate\Http\UploadedFile::fake()->create('voice.wav', 1, 'audio/wav'),
    ]);
    $response->assertStatus(202)->assertJsonPath('attempt.status', 'pending');
    $job = Queue::pushed(ProcessExerciseAttemptJob::class)->first();
    app(\App\Modules\Learning\Application\ExerciseAttemptService::class)->process($job->attemptId);
    expect(\App\Modules\Learning\Domain\Models\ExerciseAttempt::query()->latest('id')->value('score'))->toBe(94);
});

test('completed learning attempt recalculates the linked SRS card and records review context', function () {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);
    app(\App\Modules\Content\Application\CanonicalLexemeSyncService::class)->sync($lexeme);
    $lexeme->refresh();
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'lexeme_id' => $lexeme->lexeme_id,
        'content_id' => $content->id,
        'item_key' => 'word:hello',
        'state' => 'reviewing',
        'interval_days' => 1,
        'ease_factor' => 2.5,
        'next_review_at' => now()->subMinute(),
    ]);

    $response = $this->actingAs($user)->postJson('/api/learning/exercise-attempts', [
        'content_id' => $content->id,
        'content_lexeme_id' => $lexeme->id,
        'exercise_type' => 'dictation',
        'target_text' => 'hello',
        'user_text' => 'hello',
    ]);
    $job = Queue::pushed(ProcessExerciseAttemptJob::class)->first();

    app(\App\Modules\Learning\Application\ExerciseAttemptService::class)->process($job->attemptId);

    expect($card->fresh()->next_review_at->isFuture())->toBeTrue()
        ->and(SrsReview::query()->where('srs_card_id', $card->id)->value('content_lexeme_id'))->toBe($lexeme->id)
        ->and(SrsReview::query()->where('srs_card_id', $card->id)->value('exercise_type'))->toBe('dictation');
});

test('failed processing is persisted for the learner and can be inspected', function () {
    Queue::fake();
    $user = User::factory()->create();
    $content = Content::factory()->create(['language' => 'en']);
    app()->instance(SpeechToTextProviderInterface::class, new class implements SpeechToTextProviderInterface
    {
        public function transcribe(string $audioPath, string $language): array
        {
            throw new \RuntimeException('speech provider unavailable');
        }
    });

    $response = $this->actingAs($user)->post('/api/learning/exercise-attempts', [
        'content_id' => $content->id, 'exercise_type' => 'shadowing', 'target_text' => 'hello',
        'audio' => \Illuminate\Http\UploadedFile::fake()->create('voice.wav', 1, 'audio/wav'),
    ]);
    $attemptId = $response->json('attempt.id');
    $job = Queue::pushed(ProcessExerciseAttemptJob::class)->first();

    expect(fn () => app(\App\Modules\Learning\Application\ExerciseAttemptService::class)->process($job->attemptId))->toThrow(\RuntimeException::class);
    $this->actingAs($user)->getJson("/api/learning/exercise-attempts/{$attemptId}")
        ->assertOk()->assertJsonPath('attempt.status', 'failed');
});
