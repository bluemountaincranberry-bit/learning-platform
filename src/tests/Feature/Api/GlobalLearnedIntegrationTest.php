<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('marking lexeme learned in one content shows learned for same text in another content', function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole('user');

    $contentA = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Content A',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lexemeA = $contentA->lexemes()->create([
        'type' => 'word',
        'text' => 'hello',
        'sort_order' => 1,
    ]);

    $contentB = Content::query()->create([
        'type' => 'article',
        'title' => 'Content B',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $contentB->lexemes()->create([
        'type' => 'word',
        'text' => 'hello',
        'sort_order' => 1,
    ]);
    $contentB->lexemes()->create([
        'type' => 'word',
        'text' => 'world',
        'sort_order' => 2,
    ]);

    $this->actingAs($user)
        ->postJson("/api/content/lexemes/{$lexemeA->id}/mark-learned")
        ->assertOk();

    $response = $this->actingAs($user)
        ->getJson("/api/content/{$contentB->id}/lexemes")
        ->assertOk();

    $lexemes = $response->json('lexemes');
    $hello = collect($lexemes)->firstWhere('text', 'hello');
    $world = collect($lexemes)->firstWhere('text', 'world');

    expect($hello)->not->toBeNull();
    expect($hello['learned'])->toBeTrue();
    expect($world)->not->toBeNull();
    expect($world['learned'])->toBeFalse();
});
