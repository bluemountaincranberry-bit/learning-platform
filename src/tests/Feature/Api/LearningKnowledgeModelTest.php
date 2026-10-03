<?php

namespace Tests\Feature\Api;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\Srs\Domain\Models\SrsReview;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('content lexeme API exposes explainable learning selection', function () {
    $user = User::factory()->create(['current_level' => 'A1']);
    $content = Content::factory()->create(['level' => 'A1']);
    $lexeme = Lexeme::query()->create(['slug' => 'hello', 'lemma' => 'hello', 'normalized_lemma' => 'hello', 'level' => 'A1', 'status' => 'published']);
    $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'frequency' => 3, 'lexeme_id' => $lexeme->id]);

    $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.learning_category', 'essential')
        ->assertJsonPath('lexemes.0.learning_score', 85)
        ->assertJsonPath('lexemes.0.learning_reasons.0', 'repeated_in_content');
});

test('self-check records confidence dimensions for the content lexeme', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);

    $this->actingAs($user)->postJson('/api/self-check/submit', [
        'content_id' => $content->id,
        'answers' => [['content_lexeme_id' => $lexeme->id, 'known' => false, 'error_type' => 'unknown_meaning']],
    ])->assertOk();

    $this->assertDatabaseHas('user_lexeme_confidences', [
        'user_id' => $user->id,
        'content_lexeme_id' => $lexeme->id,
        'recognition' => 20,
        'recall' => 15,
        'listening' => 20,
    ]);
});

test('srs review stores exercise error context', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:hello',
        'next_review_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)->postJson('/api/srs/review', [
        'card_id' => $card->id,
        'grade' => 1,
        'content_lexeme_id' => $lexeme->id,
        'exercise_type' => 'listening',
        'error_type' => 'could_not_hear',
        'hint_used' => true,
    ])->assertOk();

    $this->assertDatabaseHas('srs_reviews', [
        'srs_card_id' => $card->id,
        'content_lexeme_id' => $lexeme->id,
        'exercise_type' => 'listening',
        'error_type' => 'could_not_hear',
        'hint_used' => true,
    ]);
});

test('profile learning goal is persisted and influences lexeme selection', function () {
    $user = User::factory()->create(['current_level' => 'A1']);
    $content = Content::factory()->create(['level' => 'A1']);
    $lexeme = $content->lexemes()->create(['type' => 'phrase', 'text' => 'Could you help me?', 'frequency' => 1]);

    $this->actingAs($user)->putJson('/api/profile', ['learning_goal' => 'conversation'])
        ->assertOk()
        ->assertJsonPath('user.learning_goal', 'conversation');

    $this->actingAs($user)->getJson("/api/content/{$content->id}/lexemes")
        ->assertOk()
        ->assertJsonPath('lexemes.0.learning_category', 'useful_phrase')
        ->assertJsonPath('lexemes.0.learning_reasons.1', 'matches_conversation_goal');
});

test('progress API exposes skill accuracy from review outcomes', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create();
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'listen']);
    $card = SrsCard::query()->create([
        'user_id' => $user->id,
        'content_id' => $content->id,
        'item_key' => 'word:listen',
        'next_review_at' => now(),
    ]);
    SrsReview::query()->create([
        'srs_card_id' => $card->id,
        'content_lexeme_id' => $lexeme->id,
        'grade' => 1,
        'exercise_type' => 'listening',
        'reviewed_at' => now(),
    ]);

    $this->actingAs($user)->getJson('/api/me/stats')
        ->assertOk()
        ->assertJsonPath('skill_accuracy.0.skill', 'listening')
        ->assertJsonPath('skill_accuracy.0.attempts', 1)
        ->assertJsonPath('skill_accuracy.0.accuracy', 0)
        ->assertJsonPath('retention.1.attempts', 0);
});
