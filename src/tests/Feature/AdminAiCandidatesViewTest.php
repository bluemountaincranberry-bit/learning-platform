<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Filament\Resources\Contents\RelationManagers\GrammarCandidatesRelationManager;
use App\Filament\Resources\Contents\RelationManagers\LexemeCandidatesRelationManager;
use App\Modules\Ai\Domain\Models\AiAnalysisRun;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-ai-candidates@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

test('admin can view content detail page with an AI analysis run', function () {
    $admin = actingAdmin();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'AI candidates test content',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'source_text' => 'I have been waiting for you to get up.',
        'created_by' => $admin->id,
    ]);
    $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);

    $response = $this->get("/admin/contents/{$content->id}");

    $response->assertSuccessful();
    $response->assertSee('AI analysis status');
});

test('lexeme candidates relation manager lists candidates for the content', function () {
    $admin = actingAdmin();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'AI candidates test content',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $candidate = $run->lexemeCandidates()->create([
        'text' => 'get up',
        'normalized_text' => 'get up',
        'type' => 'phrasal_verb',
        'translation' => 'вставать',
        'status' => 'pending',
    ]);

    Livewire::test(LexemeCandidatesRelationManager::class, [
        'ownerRecord' => $content,
        'pageClass' => ViewContent::class,
    ])->assertCanSeeTableRecords([$candidate]);
});

test('grammar candidates relation manager lists candidates for the content', function () {
    $admin = actingAdmin();

    $content = Content::query()->create([
        'type' => 'youtube',
        'title' => 'AI candidates test content',
        'language' => 'en',
        'origin' => 'curated',
        'status' => 'ready',
        'created_by' => $admin->id,
    ]);
    $run = $content->analysisRuns()->create(['status' => AiAnalysisRun::STATUS_COMPLETED]);
    $candidate = $run->grammarCandidates()->create([
        'title' => 'Present Perfect Continuous',
        'summary' => 'Unfinished past action.',
        'status' => 'pending',
    ]);

    Livewire::test(GrammarCandidatesRelationManager::class, [
        'ownerRecord' => $content,
        'pageClass' => ViewContent::class,
    ])->assertCanSeeTableRecords([$candidate]);
});
