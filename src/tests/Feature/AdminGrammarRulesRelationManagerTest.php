<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Filament\Resources\Contents\RelationManagers\GrammarRulesRelationManager;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForGrammarAttach(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-grammar-attach@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }
    test()->actingAs($admin, 'web');

    return $admin;
}

function makeGrammarRuleForAttach(string $status = GrammarRule::STATUS_PUBLISHED): GrammarRule
{
    $topic = GrammarTopic::query()->create(['slug' => 'topic-attach-'.uniqid(), 'language' => 'en', 'name' => 'Topic', 'status' => 'active']);

    return GrammarRule::query()->create([
        'topic_id' => $topic->id,
        'slug' => 'rule-attach-'.uniqid(),
        'language' => 'en',
        'title' => 'Present Perfect',
        'status' => $status,
    ]);
}

function makeContentForGrammarAttach(int $authorId): Content
{
    return Content::query()->create([
        'type' => 'youtube', 'title' => 'Attach test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'created_by' => $authorId,
    ]);
}

test('admin can directly attach a published grammar rule to content via the relation manager', function () {
    $admin = actingAdminForGrammarAttach();
    $content = makeContentForGrammarAttach($admin->id);
    $rule = makeGrammarRuleForAttach();

    Livewire::test(GrammarRulesRelationManager::class, ['ownerRecord' => $content, 'pageClass' => ViewContent::class])
        ->callTableAction('attach', data: ['recordId' => $rule->id, 'note' => 'Manually curated']);

    expect($content->fresh()->grammarRules()->pluck('grammar_rules.id'))->toContain($rule->id)
        ->and($content->fresh()->grammarRules()->first()->pivot->note)->toBe('Manually curated');
});

test('the attach picker only offers published grammar rules', function () {
    $admin = actingAdminForGrammarAttach();
    $content = makeContentForGrammarAttach($admin->id);
    $draftRule = makeGrammarRuleForAttach(GrammarRule::STATUS_DRAFT);

    Livewire::test(GrammarRulesRelationManager::class, ['ownerRecord' => $content, 'pageClass' => ViewContent::class])
        ->callTableAction('attach', data: ['recordId' => $draftRule->id]);

    expect($content->fresh()->grammarRules()->pluck('grammar_rules.id'))->not->toContain($draftRule->id);
});

test('admin can detach a directly attached grammar rule from content via the relation manager', function () {
    $admin = actingAdminForGrammarAttach();
    $content = makeContentForGrammarAttach($admin->id);
    $rule = makeGrammarRuleForAttach();
    $content->grammarRules()->attach($rule->id, ['status' => 'linked']);

    Livewire::test(GrammarRulesRelationManager::class, ['ownerRecord' => $content, 'pageClass' => ViewContent::class])
        ->callTableAction('detach', $rule);

    expect($content->fresh()->grammarRules()->pluck('grammar_rules.id'))->not->toContain($rule->id);
});

test('directly attaching a grammar rule does not require or touch an AI analysis run', function () {
    $admin = actingAdminForGrammarAttach();
    $content = makeContentForGrammarAttach($admin->id);
    $rule = makeGrammarRuleForAttach();

    Livewire::test(GrammarRulesRelationManager::class, ['ownerRecord' => $content, 'pageClass' => ViewContent::class])
        ->callTableAction('attach', data: ['recordId' => $rule->id]);

    expect($content->fresh()->latestAnalysisRun)->toBeNull();
});
