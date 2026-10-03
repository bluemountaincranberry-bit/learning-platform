<?php

namespace Tests\Feature\Learning;

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('self-check submission is idempotent and schedules a server retry once', function () {
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);
    $payload = ['content_id' => $content->id, 'operation_id' => 'session-test-1', 'answers' => [['content_lexeme_id' => $lexeme->id, 'known' => false, 'exercise_type' => 'quick-check', 'error_type' => 'unknown_meaning']]];

    $this->actingAs($user)->postJson('/api/self-check/submit', $payload)->assertOk()->assertJsonPath('idempotent', false);
    $this->actingAs($user)->postJson('/api/self-check/submit', $payload)->assertOk()->assertJsonPath('idempotent', true);

    $this->assertDatabaseCount('self_check_submissions', 1);
    $this->assertDatabaseCount('learning_retries', 1);
});

test('adaptive flow flag can roll back to the legacy recommendation fallback', function () {
    config(['learning.adaptive.enabled' => false]);
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);
    $lexeme = $content->lexemes()->create(['type' => 'word', 'text' => 'hello']);

    $this->actingAs($user)->getJson('/api/self-check/start?content_id='.$content->id)
        ->assertOk()
        ->assertJsonPath('items.0.selection_reason', 'Legacy practice fallback');
});
