<?php

use App\Modules\Content\Domain\Models\Content;
use App\Modules\Srs\Domain\Models\SrsCard;
use App\Modules\User\Models\User;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Content\Application\Contracts\LexemeServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function makeReadyContentWithLexemes(array $words): array
{
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'C', 'language' => 'en', 'origin' => 'curated', 'status' => 'ready',
    ]);
    $lexemes = [];
    foreach ($words as $i => $word) {
        $lexemes[] = $content->lexemes()->create(['type' => 'word', 'text' => $word, 'sort_order' => $i + 1]);
    }

    return [$content, $lexemes];
}

test('bulk mark-learned requires auth', function () {
    [, $lexemes] = makeReadyContentWithLexemes(['a', 'b']);

    $this->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => [$lexemes[0]->id]])
        ->assertUnauthorized();
});

test('bulk mark-learned validates ids', function () {
    $user = User::factory()->create();
    $user->assignRole('user');

    $this->actingAs($user)->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => []])
        ->assertStatus(422);

    $this->actingAs($user)->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => [999999]])
        ->assertStatus(422);

    $this->actingAs($user)->postJson('/api/content/lexemes/bulk-mark-learned', [])
        ->assertStatus(422);
});

test('bulk mark-learned marks all given words as learned', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexemes] = makeReadyContentWithLexemes(['run', 'jump', 'eat']);
    $ids = array_map(fn ($l) => $l->id, $lexemes);

    $response = $this->actingAs($user)
        ->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => $ids])
        ->assertOk();

    $response->assertJson(['succeeded' => 3, 'failed' => 0]);
    expect(UserLexemeProgress::query()->where('user_id', $user->id)->count())->toBe(3);
});

test('bulk start-learning creates due SrsCards for all given words', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexemes] = makeReadyContentWithLexemes(['sing', 'dance']);
    $ids = array_map(fn ($l) => $l->id, $lexemes);

    $response = $this->actingAs($user)
        ->postJson('/api/content/lexemes/bulk-start-learning', ['ids' => $ids])
        ->assertOk();

    $response->assertJson(['succeeded' => 2, 'failed' => 0]);
    expect(SrsCard::query()->where('user_id', $user->id)->count())->toBe(2);
});

test('a failure on one id does not lose the others in the batch', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexemes] = makeReadyContentWithLexemes(['alpha', 'beta']);
    [$good, $bad] = $lexemes;

    $fake = Mockery::mock(LexemeServiceInterface::class);
    $fake->shouldReceive('markLearned')
        ->once()
        ->with(Mockery::on(fn ($l) => $l->id === $good->id), Mockery::any());
    $fake->shouldReceive('markLearned')
        ->once()
        ->with(Mockery::on(fn ($l) => $l->id === $bad->id), Mockery::any())
        ->andThrow(new RuntimeException('simulated failure'));
    app()->instance(LexemeServiceInterface::class, $fake);

    $response = $this->actingAs($user)
        ->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => [$good->id, $bad->id]])
        ->assertOk();

    $response->assertJson(['succeeded' => 1, 'failed' => 1]);
    $results = collect($response->json('results'));
    expect($results->firstWhere('id', $good->id)['ok'])->toBeTrue();
    expect($results->firstWhere('id', $bad->id)['ok'])->toBeFalse();
});

test('bulk endpoints only affect the ids they were given', function () {
    $user = User::factory()->create();
    $user->assignRole('user');
    [, $lexemes] = makeReadyContentWithLexemes(['one', 'two', 'three']);

    $this->actingAs($user)
        ->postJson('/api/content/lexemes/bulk-mark-learned', ['ids' => [$lexemes[0]->id]])
        ->assertOk();

    $after = $this->actingAs($user)->getJson("/api/content/{$lexemes[0]->content_id}/lexemes")->json('lexemes');
    $byText = collect($after)->keyBy('text');
    expect($byText['one']['learned'])->toBeTrue();
    expect($byText['two']['learned'])->toBeFalse();
    expect($byText['three']['learned'])->toBeFalse();
});
