<?php

use App\Modules\User\Models\User;

test('ai_extraction_thoroughness is accepted, persisted and returned by the profile API', function () {
    $user = User::factory()->create(['ai_extraction_thoroughness' => null]);

    $response = $this->actingAs($user)->putJson('/api/profile', ['ai_extraction_thoroughness' => 'focused'])
        ->assertOk();

    expect($response->json('user.ai_extraction_thoroughness'))->toBe('focused')
        ->and($user->fresh()->ai_extraction_thoroughness)->toBe('focused');
});

test('ai_extraction_thoroughness can be cleared back to null', function () {
    $user = User::factory()->create(['ai_extraction_thoroughness' => 'focused']);

    $this->actingAs($user)->putJson('/api/profile', ['ai_extraction_thoroughness' => null])->assertOk();

    expect($user->fresh()->ai_extraction_thoroughness)->toBeNull();
});

test('ai_extraction_thoroughness rejects values outside the enum', function () {
    $user = User::factory()->create(['ai_extraction_thoroughness' => null]);

    $this->actingAs($user)->putJson('/api/profile', ['ai_extraction_thoroughness' => 'maximum-overdrive'])
        ->assertInvalid(['ai_extraction_thoroughness']);

    expect($user->fresh()->ai_extraction_thoroughness)->toBeNull();
});
