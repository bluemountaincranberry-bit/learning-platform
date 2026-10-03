<?php

use App\Filament\Resources\Lexemes\LexemeResource;
use App\Filament\Resources\Lexemes\Pages\CreateLexeme;
use App\Filament\Resources\Lexemes\Pages\EditLexeme;
use App\Filament\Resources\Lexemes\RelationManagers\AssociationsRelationManager;
use App\Modules\Content\Domain\Models\Lexeme;
use App\Modules\User\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function actingAdminForLexemes(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $admin = User::firstOrCreate(
        ['email' => 'admin-lexemes@example.com'],
        ['name' => 'Admin User', 'password' => bcrypt('password')]
    );
    if (! $admin->hasRole('admin')) {
        $admin->assignRole('admin');
    }
    test()->actingAs($admin, 'web');

    return $admin;
}

test('admin can view the lexemes list', function () {
    actingAdminForLexemes();
    Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    $response = test()->get(LexemeResource::getUrl('index'));

    $response->assertSuccessful();
    $response->assertSee('bathrobe');
});

test('admin can create a lexeme through the resource form', function () {
    actingAdminForLexemes();

    Livewire::test(CreateLexeme::class)
        ->fillForm([
            'lemma' => 'bathrobe',
            'slug' => 'bathrobe',
            'normalized_lemma' => 'bathrobe',
            'language' => 'en',
            'part_of_speech' => 'noun',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Lexeme::query()->where('slug', 'bathrobe')->exists())->toBeTrue();
});

test('admin can edit a lexeme through the resource form', function () {
    actingAdminForLexemes();
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);

    Livewire::test(EditLexeme::class, ['record' => $lexeme->getRouteKey()])
        ->fillForm(['notes' => 'post-bath clothing'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($lexeme->fresh()->notes)->toBe('post-bath clothing');
});

test('admin can link a synonym through the associations relation manager', function () {
    actingAdminForLexemes();
    $lexeme = Lexeme::query()->create(['slug' => 'bathrobe', 'language' => 'en', 'lemma' => 'bathrobe', 'normalized_lemma' => 'bathrobe']);
    $synonym = Lexeme::query()->create(['slug' => 'dressing-gown', 'language' => 'en', 'lemma' => 'dressing gown', 'normalized_lemma' => 'dressing gown']);

    Livewire::test(AssociationsRelationManager::class, [
        'ownerRecord' => $lexeme,
        'pageClass' => EditLexeme::class,
    ])
        ->callTableAction('create', data: [
            'related_lexeme_id' => $synonym->id,
            'type' => 'synonym',
        ])
        ->assertHasNoTableActionErrors();

    expect($lexeme->associations()->where('related_lexeme_id', $synonym->id)->where('type', 'synonym')->exists())->toBeTrue();
});
