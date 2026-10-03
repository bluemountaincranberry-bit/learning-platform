<?php

use App\Modules\Ai\Application\AiFieldEditService;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\Content\Domain\Models\LexemeAssociation;
use App\Modules\Content\Domain\Models\LexemeExample;
use App\Modules\Content\Domain\Models\LexemeTranslation;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
});

function mockMoreExamplesAiProposal(array $proposal): void
{
    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldReceive('proposePrepared')->once()->andReturn($proposal);
    app()->instance(AiFieldEditService::class, $mock);
}

test('more-examples requires auth', function () {
    $lexeme = Lexeme::query()->create(['slug' => 'run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run']);

    $this->postJson("/api/dictionary/{$lexeme->id}/more-examples")->assertUnauthorized();
});

test('more-examples returns 503 when AI feature is disabled', function () {
    Config::set('ai.enabled', false);
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = Lexeme::query()->create(['slug' => 'run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run']);

    $this->actingAs($user)
        ->postJson("/api/dictionary/{$lexeme->id}/more-examples")
        ->assertStatus(503)
        ->assertJsonFragment(['message' => 'AI feature is disabled.']);
});

test('more-examples returns fresh examples from AI and writes nothing to the catalog', function () {
    Config::set('ai.enabled', true);
    $user = User::factory()->create();
    $user->assignRole('user');
    $lexeme = Lexeme::query()->create(['slug' => 'run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run']);

    mockMoreExamplesAiProposal([
        'synonyms' => [['lemma' => 'jog', 'gloss' => 'a slower run']],
        'examples' => [
            ['example' => 'She runs every morning.', 'translation' => 'Она бегает каждое утро.'],
            ['example' => 'I run to catch the bus.', 'translation' => 'Я бегу, чтобы успеть на автобус.'],
        ],
        'translation' => 'бежать',
    ]);

    $this->actingAs($user)
        ->postJson("/api/dictionary/{$lexeme->id}/more-examples")
        ->assertOk()
        ->assertJsonPath('examples.0.example', 'She runs every morning.')
        ->assertJsonPath('examples.1.example', 'I run to catch the bus.');

    // propose() is read-only — nothing gets written to the catalog just by asking.
    expect(LexemeExample::query()->where('lexeme_id', $lexeme->id)->count())->toBe(0);
    expect(LexemeTranslation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(0);
    expect(LexemeAssociation::query()->where('lexeme_id', $lexeme->id)->count())->toBe(0);
});
