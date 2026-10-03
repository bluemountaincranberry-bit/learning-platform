<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('self-check start requires auth', function () {
    $content = Content::factory()->create(['status' => 'ready']);
    $this->getJson('/api/self-check/start?content_id='.$content->id)
        ->assertUnauthorized();
});

test('self-check submit requires auth', function () {
    $this->postJson('/api/self-check/submit', [
        'content_id' => 1,
        'answers' => [['content_lexeme_id' => 1, 'known' => true]],
    ])->assertUnauthorized();
});

test('self-check start returns items for content with learned lexemes', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'ready']);
    $lex1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $lex2 = $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex1->id,
        'learned_at' => now(),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex2->id,
        'learned_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->getJson('/api/self-check/start?content_id='.$content->id.'&limit=10')
        ->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(2);
    expect(collect($items)->pluck('content_lexeme_id')->all())->toContain($lex1->id, $lex2->id);
    expect(collect($items)->pluck('lexeme_display')->all())->toContain('hello', 'world');
});

test('self-check start returns subset when limit is set', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'ready']);
    foreach (['a', 'b', 'c', 'd', 'e'] as $i => $text) {
        $content->lexemes()->create(['type' => 'word', 'text' => $text, 'sort_order' => $i + 1]);
    }
    foreach ($content->lexemes as $lex) {
        UserLexemeProgress::query()->create([
            'user_id' => $user->id,
            'content_lexeme_id' => $lex->id,
            'learned_at' => now(),
        ]);
    }

    $response = $this->actingAs($user)
        ->getJson('/api/self-check/start?content_id='.$content->id.'&limit=3')
        ->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(3);
});

test('self-check start returns 404 for non-ready content', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'draft']);
    $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->getJson('/api/self-check/start?content_id='.$content->id)
        ->assertNotFound();
});

test('self-check submit returns score', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'ready']);
    $lex1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);
    $lex2 = $content->lexemes()->create(['type' => 'word', 'text' => 'world', 'sort_order' => 2]);

    $response = $this->actingAs($user)
        ->postJson('/api/self-check/submit', [
            'content_id' => $content->id,
            'answers' => [
                ['content_lexeme_id' => $lex1->id, 'known' => true],
                ['content_lexeme_id' => $lex2->id, 'known' => false],
            ],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(2);
    expect($response->json('correct'))->toBe(1);
    expect((float) $response->json('score_pct'))->toBe(50.0);
});

test('self-check submit ignores answers for lexemes not in content', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'ready']);
    $lex1 = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $otherContent = Content::factory()->create(['status' => 'ready']);
    $otherLex = $otherContent->lexemes()->create(['type' => 'word', 'text' => 'other', 'sort_order' => 1]);

    $response = $this->actingAs($user)
        ->postJson('/api/self-check/submit', [
            'content_id' => $content->id,
            'answers' => [
                ['content_lexeme_id' => $lex1->id, 'known' => true],
                ['content_lexeme_id' => $otherLex->id, 'known' => true],
            ],
        ])
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('correct'))->toBe(1);
    expect((float) $response->json('score_pct'))->toBe(100.0);
});

test('self-check submit returns 404 for non-ready content', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $content = Content::factory()->create(['status' => 'draft']);
    $lex = $content->lexemes()->create(['type' => 'word', 'text' => 'hello', 'sort_order' => 1]);

    $this->actingAs($user)
        ->postJson('/api/self-check/submit', [
            'content_id' => $content->id,
            'answers' => [['content_lexeme_id' => $lex->id, 'known' => true]],
        ])
        ->assertNotFound();
});
