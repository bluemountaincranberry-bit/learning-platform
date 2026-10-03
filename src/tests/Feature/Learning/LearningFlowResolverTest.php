<?php

namespace Tests\Feature\Learning;

use App\Modules\Learning\Domain\Models\LearningFlowAssignment;
use App\Modules\Learning\Domain\Models\LearningFlowProfile;
use App\Modules\User\Models\User;
use App\Modules\Learning\Application\LearningFlowDefaults;
use App\Modules\Learning\Application\LearningFlowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('resolver uses the published user assignment before a scoped assignment', function () {
    $user = User::factory()->create(['current_level' => 'B1', 'learning_goal' => 'conversation', 'translation_language' => 'ru']);
    $scope = LearningFlowProfile::query()->create(['name' => 'Scope', 'slug' => 'scope', 'status' => 'published', 'config' => ['session_minutes' => 20]]);
    $personal = LearningFlowProfile::query()->create(['name' => 'Personal', 'slug' => 'personal', 'status' => 'published', 'config' => ['session_minutes' => 5]]);
    LearningFlowAssignment::query()->create(['learning_flow_profile_id' => $scope->id, 'level' => 'B1', 'priority' => 100]);
    LearningFlowAssignment::query()->create(['learning_flow_profile_id' => $personal->id, 'user_id' => $user->id, 'priority' => 1]);

    $resolved = app(LearningFlowResolver::class)->resolve($user);

    expect($resolved['profile']->is($personal))->toBeTrue()
        ->and($resolved['source'])->toBe('user')
        ->and($resolved['config']['session_minutes'])->toBe(5)
        ->and($resolved['config']['daily_new_words'])->toBe(8);
});

test('resolver applies the matching scope and ignores draft profiles', function () {
    $user = User::factory()->create(['current_level' => 'A2', 'learning_goal' => 'travel', 'translation_language' => 'ru']);
    $published = LearningFlowProfile::query()->create(['name' => 'Travel', 'slug' => 'travel', 'status' => 'published', 'config' => LearningFlowDefaults::balanced()]);
    LearningFlowProfile::query()->create(['name' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'config' => ['session_minutes' => 99]]);
    LearningFlowAssignment::query()->create(['learning_flow_profile_id' => $published->id, 'language' => 'ru', 'level' => 'A2', 'learning_goal' => 'travel', 'priority' => 10]);

    $resolved = app(LearningFlowResolver::class)->resolve($user);

    expect($resolved['profile']->is($published))->toBeTrue()
        ->and($resolved['source'])->toBe('scope');
});

test('resolver falls back to balanced defaults when no published profile exists', function () {
    $user = User::factory()->create();

    $resolved = app(LearningFlowResolver::class)->resolve($user);

    expect($resolved['profile']->slug)->toBe('balanced')
        ->and($resolved['source'])->toBe('default')
        ->and($resolved['config']['stages'])->toContain('recognition');
});

test('learner can update only safe flow preferences and receives effective config', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->putJson('/api/learning/flow/preferences', [
        'session_minutes' => 25,
        'listening_weight' => 35,
        'difficulty_preference' => 'harder',
    ])->assertOk()
        ->assertJsonPath('preferences.session_minutes', 25)
        ->assertJsonPath('flow.config.session_minutes', 25)
        ->assertJsonPath('flow.config.activity_weights.listening', 35)
        ->assertJsonPath('flow.config.difficulty_preference', 'harder');

    $this->actingAs($user)->putJson('/api/learning/flow/preferences', [
        'session_minutes' => 1,
        'target_success_rate' => 0.1,
    ])->assertUnprocessable();
});

test('learner can choose a published recommended profile while admin assignment remains stronger', function () {
    $user = User::factory()->create();
    $profile = LearningFlowProfile::query()->create(['name' => 'Listening', 'slug' => 'listening-choice', 'status' => 'published', 'config' => ['session_minutes' => 25]]);

    $this->actingAs($user)->putJson('/api/learning/flow/preferences', ['learning_flow_profile_id' => $profile->id])->assertOk();
    $this->actingAs($user)->getJson('/api/learning/flow')->assertOk()->assertJsonPath('profile.slug', 'listening-choice')->assertJsonPath('profile.source', 'learner');
});
