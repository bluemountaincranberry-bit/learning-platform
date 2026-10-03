<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\ContentLexeme;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('profile get returns daily_goal today_learned_count streak_days', function () {
    $user = User::factory()->create(['daily_goal' => 10]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('user.daily_goal', 10)
        ->assertJsonPath('today_learned_count', 0)
        ->assertJsonPath('streak_days', 0);
});

test('profile put updates daily_goal', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)
        ->putJson('/api/profile', ['daily_goal' => 5])
        ->assertOk()
        ->assertJsonPath('user.daily_goal', 5);

    expect($user->fresh()->daily_goal)->toBe(5);
});

test('today_learned_count and streak_days computed from user_lexeme_progress', function () {
    $user = User::factory()->create(['daily_goal' => 10, 'timezone' => 'UTC']);
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex1 = $content->lexemes()->create(['type' => 'word', 'text' => 'one', 'sort_order' => 1]);
    $lex2 = $content->lexemes()->create(['type' => 'word', 'text' => 'two', 'sort_order' => 2]);
    $lex3 = $content->lexemes()->create(['type' => 'word', 'text' => 'three', 'sort_order' => 3]);

    // Today: 2 progress rows
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex1->id,
        'learned_at' => now(),
        'created_at' => now(),
    ]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex2->id,
        'learned_at' => now(),
        'created_at' => now(),
    ]);
    // Yesterday: 1 progress row (for streak)
    $yesterday = Carbon::now()->subDay();
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex3->id,
        'learned_at' => $yesterday,
        'created_at' => $yesterday,
    ]);

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('user.daily_goal', 10)
        ->assertJsonPath('today_learned_count', 2)
        ->assertJsonPath('streak_days', 2);
});

test('streak is zero when no activity today', function () {
    $user = User::factory()->create(['timezone' => 'UTC']);
    $user->assignRole('user');

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'C',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $lex = $content->lexemes()->create(['type' => 'word', 'text' => 'one', 'sort_order' => 1]);

    $yesterday = Carbon::now()->subDay();
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex->id,
        'learned_at' => $yesterday,
        'created_at' => $yesterday,
    ]);

    $this->actingAs($user)
        ->getJson('/api/profile')
        ->assertOk()
        ->assertJsonPath('today_learned_count', 0)
        ->assertJsonPath('streak_days', 0);
});
