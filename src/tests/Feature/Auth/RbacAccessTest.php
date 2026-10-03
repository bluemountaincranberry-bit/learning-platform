<?php

use App\Modules\Learning\Domain\Models\UserGrammarRule;
use App\Modules\Learning\Domain\Models\UserLexemeConfidence;
use App\Modules\Learning\Domain\Models\UserLexemeContextCheck;
use App\Modules\Learning\Domain\Models\UserLexemeProgress;
use App\Modules\Learning\Domain\Models\UserLexemeSkip;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserLearningPreference;

test('user aggregate is owned by the User module', function () {
    expect((new User)->getTable())->toBe('users');
});

test('user learning preference is owned by the User module', function () {
    expect((new UserLearningPreference)->getTable())->toBe('user_learning_preferences');
});

test('user lexeme progress is owned by the Learning module', function () {
    expect((new UserLexemeProgress)->getTable())->toBe('user_lexeme_progress');
});

test('user lexeme learner state is owned by the Learning module', function () {
    expect((new UserLexemeSkip)->getTable())->toBe('user_lexeme_skips')
        ->and((new UserLexemeConfidence)->getTable())->toBe('user_lexeme_confidences')
        ->and((new UserLexemeContextCheck)->getTable())->toBe('user_lexeme_context_checks');
});

test('user grammar progress is owned by the Learning module', function () {
    expect((new UserGrammarRule)->getTable())->toBe('user_grammar_rules');
});
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

test('rbac gates enforce admin panel access by role', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'moderator', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $editor = User::factory()->create();
    $editor->assignRole('editor');

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $regular = User::factory()->create();
    $regular->assignRole('user');

    expect(Gate::forUser($admin)->allows('access-admin-panel'))->toBeTrue();
    expect(Gate::forUser($editor)->allows('access-admin-panel'))->toBeTrue();
    expect(Gate::forUser($moderator)->allows('access-admin-panel'))->toBeTrue();
    expect(Gate::forUser($regular)->allows('access-admin-panel'))->toBeFalse();
});

test('canonical user reads legacy persisted role morphs', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);

    $admin = \App\Modules\User\Models\User::factory()->create();
    $admin->assignRole('admin');

    expect($admin->getRoleNames()->all())->toBe(['admin'])
        ->and($admin->getMorphClass())->toBe('App\\Models\\User')
        ->and(Gate::forUser($admin)->allows('access-admin-panel'))->toBeTrue();
});
