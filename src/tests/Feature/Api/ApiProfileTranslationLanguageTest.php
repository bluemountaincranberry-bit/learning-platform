<?php

use App\Modules\User\Models\User;

test('translation_language is accepted, persisted and returned by the profile API', function () {
    $user = User::factory()->create(['translation_language' => null]);

    $response = $this->actingAs($user)->putJson('/api/profile', ['translation_language' => 'es'])
        ->assertOk();

    expect($response->json('user.translation_language'))->toBe('es')
        ->and($user->fresh()->translation_language)->toBe('es');
});

test('translation_language can be cleared back to null', function () {
    $user = User::factory()->create(['translation_language' => 'ru']);

    $this->actingAs($user)->putJson('/api/profile', ['translation_language' => null])->assertOk();

    expect($user->fresh()->translation_language)->toBeNull();
});

test('GET profile exposes the users translation_language', function () {
    $user = User::factory()->create(['translation_language' => 'de']);

    $response = $this->actingAs($user)->getJson('/api/profile')->assertOk();

    expect($response->json('user.translation_language'))->toBe('de');
});
