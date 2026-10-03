<?php

use App\Modules\User\Models\User;

test('current_level is accepted, persisted and returned by the profile API', function () {
    $user = User::factory()->create(['current_level' => null]);

    $response = $this->actingAs($user)->putJson('/api/profile', ['current_level' => 'B1'])
        ->assertOk();

    expect($response->json('user.current_level'))->toBe('B1')
        ->and($user->fresh()->current_level)->toBe('B1');
});

test('current_level can be cleared back to null', function () {
    $user = User::factory()->create(['current_level' => 'B1']);

    $this->actingAs($user)->putJson('/api/profile', ['current_level' => null])->assertOk();

    expect($user->fresh()->current_level)->toBeNull();
});

test('current_level rejects values outside the CEFR enum', function () {
    $user = User::factory()->create(['current_level' => null]);

    $this->actingAs($user)->putJson('/api/profile', ['current_level' => 'Z9'])
        ->assertInvalid(['current_level']);

    expect($user->fresh()->current_level)->toBeNull();
});

test('GET profile exposes the users current_level', function () {
    $user = User::factory()->create(['current_level' => 'C1']);

    $response = $this->actingAs($user)->getJson('/api/profile')->assertOk();

    expect($response->json('user.current_level'))->toBe('C1');
});
