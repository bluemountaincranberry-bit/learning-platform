<?php

use App\Filament\Resources\GrammarRules\GrammarRuleResource;
use App\Filament\Resources\GrammarRules\Pages\CreateGrammarRule;
use App\Filament\Resources\GrammarRules\Pages\EditGrammarRule;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use App\Modules\Ai\Application\AiFieldEditService;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForGrammar(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-grammar@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }
    test()->actingAs($admin, 'web');

    return $admin;
}

test('admin can view the grammar rules list', function () {
    actingAdminForGrammar();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft']);

    $response = test()->get(GrammarRuleResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('Present Simple');
});

test('admin can create a grammar rule through the resource form', function () {
    actingAdminForGrammar();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);

    Livewire::test(CreateGrammarRule::class)
        ->fillForm([
            'topic_id' => $topic->id,
            'title' => 'Past Simple',
            'slug' => 'past-simple',
            'language' => 'en',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(GrammarRule::query()->where('slug', 'past-simple')->exists())->toBeTrue();
});

test('admin can edit a grammar rule body through the resource form', function () {
    actingAdminForGrammar();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft']);

    Livewire::test(EditGrammarRule::class, ['record' => $rule->getRouteKey()])
        ->fillForm(['body' => '## Rule\nManually written.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rule->fresh()->body)->toContain('Manually written.');
});

test('editing a grammar rule through the resource creates a revision', function () {
    actingAdminForGrammar();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft']);
    $revisionsBefore = $rule->revisions()->count();

    Livewire::test(EditGrammarRule::class, ['record' => $rule->getRouteKey()])
        ->fillForm(['title' => 'Present Simple Tense'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rule->revisions()->count())->toBe($revisionsBefore + 1);
});

test('AI draft action fills the live form without saving to the database', function () {
    config(['ai.enabled' => true]);
    actingAdminForGrammar();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-x', 'language' => 'en', 'name' => 'Topic X', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-x', 'language' => 'en', 'title' => 'Present Simple', 'status' => 'draft']);

    $mock = Mockery::mock(AiFieldEditService::class);
    $mock->shouldReceive('propose')->once()->andReturn([
        'summary' => 'AI generated summary.',
        'body' => '## Rule\nAI generated body.',
    ]);
    app()->instance(AiFieldEditService::class, $mock);

    $component = Livewire::test(EditGrammarRule::class, ['record' => $rule->getRouteKey()])
        ->callAction('aiDraftGrammarContent');

    // Not persisted yet — only the in-memory form state changed.
    expect($rule->fresh()->body)->toBeNull();

    $component->assertFormSet(['summary' => 'AI generated summary.']);
});
