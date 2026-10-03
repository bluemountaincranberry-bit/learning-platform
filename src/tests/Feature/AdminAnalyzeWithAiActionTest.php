<?php

use App\Filament\Resources\Contents\Pages\ViewContent;
use App\Modules\Content\Domain\Models\Content;
use App\Modules\Content\Domain\Models\GrammarRule;
use App\Modules\Content\Domain\Models\GrammarTopic;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForAnalyze(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-ai-analyze@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }

    test()->actingAs($admin, 'web');

    return $admin;
}

test('submitting the analyze form stores the config on the created run', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    $topic = GrammarTopic::query()->create(['slug' => 'topic-y', 'language' => 'en', 'name' => 'Topic Y', 'status' => 'active']);
    $rule = GrammarRule::query()->create([
        'topic_id' => $topic->id, 'slug' => 'past-simple', 'language' => 'en', 'title' => 'Past Simple', 'status' => GrammarRule::STATUS_PUBLISHED,
    ]);
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Analyze form test', 'language' => 'en', 'level' => 'B1',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi', data: [
            'target_level' => 'A2',
            'exclude_known_words' => false,
            'exclude_words' => "run\njump",
            'exclude_grammar_rule_ids' => [$rule->id],
            'extra_instructions' => 'Focus on idioms.',
            'thoroughness' => 'thorough',
        ]);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run)->not->toBeNull()
        ->and($run->config['target_level'])->toBe('A2')
        ->and($run->config['exclude_words'])->toBe(['run', 'jump'])
        ->and($run->config['exclude_grammar_rule_ids'])->toBe([$rule->id])
        ->and($run->config['extra_instructions'])->toBe('Focus on idioms.')
        ->and($run->config['thoroughness'])->toBe('thorough');
});

test('target level defaults to the content level in the form', function () {
    config(['ai.enabled' => true]);

    $admin = actingAdminForAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Default level test', 'language' => 'en', 'level' => 'B2',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->mountAction('analyzeWithAi')
        ->assertSchemaStateSet(['target_level' => 'B2']);
});

test('exclude_known_words true pulls published lexemes for the content language into config', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    Lexeme::query()->create(['slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED]);
    Lexeme::query()->create(['slug' => 'en-jump', 'language' => 'en', 'lemma' => 'jump', 'normalized_lemma' => 'jump', 'status' => Lexeme::STATUS_PUBLISHED]);
    // Different language — must not leak into the exclude list.
    Lexeme::query()->create(['slug' => 'fr-courir', 'language' => 'fr', 'lemma' => 'courir', 'normalized_lemma' => 'courir', 'status' => Lexeme::STATUS_PUBLISHED]);

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Known words test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi', data: ['exclude_known_words' => true]);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run->config['exclude_words'])->toEqualCanonicalizing(['run', 'jump']);
});

test('exclude_known_words merges catalog words with manually entered ones without duplicates', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    Lexeme::query()->create(['slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED]);

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Merge test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi', data: ['exclude_known_words' => true, 'exclude_words' => "run\njump"]);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run->config['exclude_words'])->toEqualCanonicalizing(['run', 'jump']);
});

test('exclude_known_words false does not pull catalog words even when they exist', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    Lexeme::query()->create(['slug' => 'en-run', 'language' => 'en', 'lemma' => 'run', 'normalized_lemma' => 'run', 'status' => Lexeme::STATUS_PUBLISHED]);

    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Opt out test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi', data: ['exclude_known_words' => false]);

    $run = $content->fresh()->latestAnalysisRun;
    expect($run->config['exclude_words'])->toBe([]);
});

test('exclude_known_words checkbox defaults to checked in the form', function () {
    config(['ai.enabled' => true]);

    $admin = actingAdminForAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Checkbox default test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->mountAction('analyzeWithAi')
        ->assertSchemaStateSet(['exclude_known_words' => true]);
});

test('analyze form defaults to focused thoroughness when nothing submitted', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Defaults test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi');

    $run = $content->fresh()->latestAnalysisRun;
    expect($run->config['thoroughness'])->toBe('focused')
        ->and($run->config['exclude_words'])->toBe([]);
});

test('translation_language defaults from the admin profile setting', function () {
    config(['ai.enabled' => true]);

    $admin = actingAdminForAnalyze();
    $admin->update(['translation_language' => 'de']);
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Profile default test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->mountAction('analyzeWithAi')
        ->assertSchemaStateSet(['translation_language' => 'de']);
});

test('translation_language falls back to the global config when the admin profile has none set', function () {
    config(['ai.enabled' => true, 'ai.analysis.translation_language' => 'ru']);

    $admin = actingAdminForAnalyze();
    expect($admin->translation_language)->toBeNull();
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Global fallback test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->mountAction('analyzeWithAi')
        ->assertSchemaStateSet(['translation_language' => 'ru']);
});

test('translation_language is overridable per run', function () {
    config(['ai.enabled' => true]);
    Queue::fake();

    $admin = actingAdminForAnalyze();
    $admin->update(['translation_language' => 'de']);
    $content = Content::query()->create([
        'type' => 'youtube', 'title' => 'Override test', 'language' => 'en',
        'origin' => 'curated', 'status' => 'ready', 'source_text' => 'Some transcript text.', 'created_by' => $admin->id,
    ]);

    Livewire::test(ViewContent::class, ['record' => $content->getRouteKey()])
        ->callAction('analyzeWithAi', data: ['translation_language' => 'es']);

    expect($content->fresh()->latestAnalysisRun->config['translation_language'])->toBe('es');
});
