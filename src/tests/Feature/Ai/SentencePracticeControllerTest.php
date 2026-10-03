<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sentence practice hides content that the learner cannot view', function () {
    config(['ai.enabled' => true]);
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'draft']);

    $this->actingAs($user)
        ->postJson('/api/practice/sentences/start', [
            'direction' => 'to_target',
            'content_id' => $content->id,
        ])
        ->assertNotFound();
});

test('sentence practice accepts public content through the view policy', function () {
    config(['ai.enabled' => true]);
    $user = User::factory()->create();
    $content = Content::factory()->create(['status' => 'ready']);

    $this->actingAs($user)
        ->postJson('/api/practice/sentences/start', [
            'direction' => 'to_target',
            'content_id' => $content->id,
        ])
        ->assertOk()
        ->assertJsonPath('cards', []);
});
