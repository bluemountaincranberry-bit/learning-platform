<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Learning\Domain\Models\LearningProgress;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

test('GET /api/me/stats requires auth', function () {
    $this->getJson('/api/me/stats')->assertUnauthorized();
});

test('GET /api/me/stats returns correct structure for empty user', function () {
    $user = User::factory()->create(['daily_goal' => 5]);
    $user->assignRole('user');

    $this->actingAs($user)
        ->getJson('/api/me/stats')
        ->assertOk()
        ->assertJsonStructure([
            'overview' => [
                'total_learned',
                'today_count',
                'streak',
                'daily_goal',
                'week_count',
                'month_count',
            ],
            'by_content' => ['best', 'weak'],
            'by_language_level',
            'weak_words',
            'recommendations',
        ])
        ->assertJsonPath('overview.total_learned', 0)
        ->assertJsonPath('overview.today_count', 0)
        ->assertJsonPath('overview.streak', 0)
        ->assertJsonPath('overview.daily_goal', 5)
        ->assertJsonPath('overview.week_count', 0)
        ->assertJsonPath('overview.month_count', 0)
        ->assertJsonPath('by_content.best', [])
        ->assertJsonPath('by_content.weak', [])
        ->assertJsonPath('by_language_level', [])
        ->assertJsonPath('weak_words', [])
        ->assertJsonPath('recommendations', []);
});

test('GET /api/me/stats returns overview and by_content for user with progress', function () {
    $user = User::factory()->create(['daily_goal' => 10]);
    $user->assignRole('user');

    $content1 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Strong Content',
        'language' => 'en',
        'level' => 'A2',
        'origin' => 'curated',
        'status' => 'ready',
    ]);
    $content2 = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Weak Content',
        'language' => 'en',
        'level' => 'B1',
        'origin' => 'curated',
        'status' => 'ready',
    ]);

    $lex1 = $content1->lexemes()->create(['type' => 'word', 'text' => 'a', 'sort_order' => 1]);
    UserLexemeProgress::query()->create([
        'user_id' => $user->id,
        'content_lexeme_id' => $lex1->id,
        'learned_at' => now(),
        'created_at' => now(),
    ]);

    LearningProgress::query()->create([
        'user_id' => $user->id,
        'content_id' => $content1->id,
        'total_answers' => 10,
        'known_answers' => 9,
        'unknown_answers' => 1,
        'accuracy' => 90.0,
    ]);
    LearningProgress::query()->create([
        'user_id' => $user->id,
        'content_id' => $content2->id,
        'total_answers' => 10,
        'known_answers' => 5,
        'unknown_answers' => 5,
        'accuracy' => 50.0,
    ]);

    $response = $this->actingAs($user)->getJson('/api/me/stats')->assertOk();

    $response->assertJsonPath('overview.total_learned', 1);
    $response->assertJsonPath('overview.daily_goal', 10);

    $best = $response->json('by_content.best');
    $weak = $response->json('by_content.weak');
    expect($best)->toHaveCount(2);
    expect($weak)->toHaveCount(2);
    expect($best[0]['title'])->toBe('Strong Content');
    expect($best[0]['accuracy'])->toEqual(90);
    expect($weak[0]['title'])->toBe('Weak Content');
    expect($weak[0]['accuracy'])->toEqual(50);

    $byLevel = $response->json('by_language_level');
    expect($byLevel)->toHaveCount(2);

    $recommendations = $response->json('recommendations');
    expect($recommendations)->not->toBeEmpty();
    expect(collect($recommendations)->pluck('type')->toArray())->toContain('review');
});
