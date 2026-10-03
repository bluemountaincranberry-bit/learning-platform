<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForApply(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-ai-apply@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

test('apply action on the content page promotes accepted candidates to the catalog', function () {
    $admin = actingAdminForApply();
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Apply action test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'accepted',
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('applyAiCandidates');

    expect(Lexeme::query()->where('normalized_lemma', 'get up')->exists())->toBeTrue();
});

test('apply action is hidden when there are no accepted candidates', function () {
    $admin = actingAdminForApply();
    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'Apply action hidden test',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $run->lexemeCandidates()->create([
        'text' => 'get up', 'normalized_text' => 'get up', 'type' => 'phrasal_verb', 'status' => 'pending',
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->assertActionHidden('applyAiCandidates');
});
