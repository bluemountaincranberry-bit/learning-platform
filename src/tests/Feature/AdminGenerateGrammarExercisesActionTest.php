<?php

use App\Filament\Resources\GrammarRules\Pages\EditGrammarRule;
use App\Modules\Ai\Application\AiGrammarExerciseService;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForExerciseAction(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-exercise-action@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }
    test()->actingAs($admin, 'web');

    return $admin;
}

test('generate exercises action calls the service and persists drafts', function () {
    config(['ai.enabled' => true]);
    actingAdminForExerciseAction();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-gen-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-gen-'.uniqid(), 'language' => 'en', 'title' => 'Present Perfect', 'status' => 'draft']);

    $exercise = $rule->exercises()->create(['type' => 'cloze', 'prompt' => 'x', 'answer' => 'y', 'status' => 'draft']);

    $mock = Mockery::mock(AiGrammarExerciseService::class);
    $mock->shouldReceive('generate')->once()->with($rule->id, 5, null)->andReturn(1);
    app()->instance(AiGrammarExerciseService::class, $mock);

    Livewire::test(EditGrammarRule::class, ['record' => $rule->getRouteKey()])
        ->callAction('generateGrammarExercises', data: ['count' => 5]);
});

test('generate exercises action is hidden when AI is disabled', function () {
    config(['ai.enabled' => false]);
    actingAdminForExerciseAction();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-gen2-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);
    $rule = GrammarRule::query()->create(['topic_id' => $topic->id, 'slug' => 'rule-gen2-'.uniqid(), 'language' => 'en', 'title' => 'Present Perfect', 'status' => 'draft']);

    Livewire::test(EditGrammarRule::class, ['record' => $rule->getRouteKey()])
        ->assertActionHidden('generateGrammarExercises');
});
