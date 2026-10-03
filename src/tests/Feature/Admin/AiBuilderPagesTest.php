<?php

use App\Filament\Pages\GraphDefinitions;
use App\Filament\Pages\PromptTemplates;
use App\Modules\Ai\Domain\Models\PersistedGraphDefinition;
use App\Modules\Ai\Domain\Models\PromptTemplate;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAdminForAiBuilderPages(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    test()->actingAs($admin, 'web');

    return $admin;
}

function actingEditorForAiBuilderPages(): User
{
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    test()->actingAs($editor, 'web');

    return $editor;
}

test('PromptTemplates page renders for admin and lists existing templates', function () {
    actingAdminForAiBuilderPages();

    $template = PromptTemplate::query()->create(['key' => 'ai_explain_lexeme', 'name' => 'Explain lexeme']);
    $version = $template->versions()->create(['version' => 1, 'system_template' => 'sys', 'user_template' => 'usr']);
    $template->update(['active_version_id' => $version->id]);

    Livewire::test(PromptTemplates::class)
        ->assertOk()
        ->assertSee('ai_explain_lexeme')
        ->assertSee('v1');
});

test('PromptTemplates page shows an empty state with no overrides', function () {
    actingAdminForAiBuilderPages();

    Livewire::test(PromptTemplates::class)
        ->assertOk()
        ->assertSee('No prompt template overrides yet');
});

test('PromptTemplates page is not accessible to an editor (admin-only, stricter than the general admin panel gate)', function () {
    actingEditorForAiBuilderPages();

    expect(\App\Filament\Pages\PromptTemplates::canAccess())->toBeFalse();
});

test('GraphDefinitions page renders for admin and lists existing definitions', function () {
    actingAdminForAiBuilderPages();

    $record = PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);
    $version = $record->versions()->create([
        'version' => 1,
        'nodes' => [['key' => 'a', 'node' => 'analyze']],
        'edges' => [],
    ]);
    $record->update(['active_version_id' => $version->id]);

    Livewire::test(GraphDefinitions::class)
        ->assertOk()
        ->assertSee('ai_analysis')
        ->assertSee('v1');
});

test('GraphDefinitions page shows an empty state with no overrides', function () {
    actingAdminForAiBuilderPages();

    Livewire::test(GraphDefinitions::class)
        ->assertOk()
        ->assertSee('No graph definition overrides yet');
});

test('GraphDefinitions page is not accessible to an editor', function () {
    actingEditorForAiBuilderPages();

    expect(\App\Filament\Pages\GraphDefinitions::canAccess())->toBeFalse();
});

test('GraphDefinitions page links to the canvas for config-registered graphs that have no DB override yet', function () {
    actingAdminForAiBuilderPages();

    Livewire::test(GraphDefinitions::class)
        ->assertOk()
        ->assertSee('ai_analysis — open in canvas', false);
});

test('GraphDefinitions page stops listing a graph_name under "running from code" once it has a draft', function () {
    actingAdminForAiBuilderPages();

    PersistedGraphDefinition::query()->create(['key' => 'ai_analysis', 'name' => 'AI analysis']);

    Livewire::test(GraphDefinitions::class)
        ->assertOk()
        ->assertDontSee('ai_analysis — open in canvas', false);
});
