<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('my words requires auth', function () {
    $this->getJson('/api/me/words')
        ->assertUnauthorized();
});

test('my words shows words in learning before known words', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Demo',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $learning = $content->lexemes()->create(['type' => 'word', 'text' => 'run', 'sort_order' => 1]);
    $known = $content->lexemes()->create(['type' => 'word', 'text' => 'walk', 'sort_order' => 2]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$learning->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$known->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/me/words')->assertOk();

    expect($response->json('data.0.lexeme'))->toBe('run');
    expect($response->json('data.0.status'))->toBe('in_learning');
    expect($response->json('data.1.lexeme'))->toBe('walk');
    expect($response->json('data.1.status'))->toBe('known');
});

test('my words filters by status content and level', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $contentA = Content::query()->create([
        'type' => 'youtube',
        'title' => 'A',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $contentB = Content::query()->create([
        'type' => 'youtube',
        'title' => 'B',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $a1 = $contentA->lexemes()->create(['type' => 'word', 'text' => 'apple', 'sort_order' => 1]);
    $b1 = $contentB->lexemes()->create(['type' => 'word', 'text' => 'banana', 'sort_order' => 1]);

    $a1->canonicalLexeme()->update(['level' => 'A1']);
    $b1->canonicalLexeme()->update(['level' => 'B1']);

    $response = $this->actingAs($user)
        ->getJson("/api/me/words?status=new&content_id={$contentA->id}&level=A1")
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.lexeme'))->toBe('apple');
    expect($response->json('data.0.status'))->toBe('new');
    expect($response->json('data.0.level'))->toBe('A1');
    expect($response->json('data.0.contexts.0.content_id'))->toBe($contentA->id);
});

test('my words can return only words in learning', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Demo',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $learning = $content->lexemes()->create(['type' => 'word', 'text' => 'focus', 'sort_order' => 1]);
    $known = $content->lexemes()->create(['type' => 'word', 'text' => 'easy', 'sort_order' => 2]);

    $this->actingAs($user)->postJson("/api/content/lexemes/{$learning->id}/start-learning")->assertOk();
    $this->actingAs($user)->postJson("/api/content/lexemes/{$known->id}/mark-learned")->assertOk();

    $response = $this->actingAs($user)->getJson('/api/me/words?status=in_learning')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.lexeme'))->toBe('focus');
    expect($response->json('data.0.in_review'))->toBeTrue();
});
